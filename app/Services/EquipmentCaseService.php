<?php

namespace App\Services;

use App\Enums\ReturnCondition;
use App\Models\Equipment;
use App\Models\EquipmentCase;
use App\Models\EquipmentCaseEvent;
use App\Models\EquipmentSerialNumber;
use App\Models\ItemReturn;
use App\Models\User;
use App\Support\EquipmentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Controlled equipment repair / warranty / disposal workflow (Revision 7).
 *
 * Every transition records reason, date, responsible user and supporting reference in
 * equipment_case_events. Units inside a case are removed from the issuable listing quantity
 * and their serial status mirrors the case, so they are blocked from issuance.
 */
class EquipmentCaseService
{
    public function __construct(
        private readonly EquipmentStockService $equipmentStock,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * @param  array{origin: string, equipment_id?: int|null, item_return_id?: int|null, serial_number_id?: int|null, quantity?: int|null, status: string, reason: string, event_date: string, reference?: string|null}  $data
     */
    public function open(User $user, array $data): EquipmentCase
    {
        $status = $data['status'];
        if (! in_array($status, [EquipmentStatus::FOR_REPAIR, EquipmentStatus::FOR_DISPOSAL], true)) {
            throw new InvalidArgumentException('A case can only be opened as For Repair or For Disposal.');
        }

        $reportedOn = Carbon::parse($data['event_date'])->startOfDay();

        $case = DB::transaction(function () use ($user, $data, $status, $reportedOn) {
            $origin = $data['origin'];
            $quantity = max(1, (int) ($data['quantity'] ?? 1));
            $serial = null;
            $return = null;

            if ($origin === EquipmentCase::ORIGIN_STOCK) {
                $equipment = Equipment::query()->lockForUpdate()->find($data['equipment_id'] ?? null);
                if (! $equipment) {
                    throw new InvalidArgumentException('Select the equipment listing the unit belongs to.');
                }

                $inStock = $equipment->serialNumbers()->inStock()->lockForUpdate()->get();
                if ($inStock->isNotEmpty() || ! empty($data['serial_number_id'])) {
                    $serial = $inStock->firstWhere('id', (int) ($data['serial_number_id'] ?? 0));
                    if (! $serial) {
                        throw new InvalidArgumentException(
                            "Select an available serial number of {$equipment->name}. Units that are issued or already in a case cannot be selected."
                        );
                    }
                    $quantity = 1;
                }

                if ($quantity > (int) $equipment->quantity) {
                    throw new InvalidArgumentException(
                        "Only {$equipment->quantity} available unit(s) of {$equipment->name} are in Supply Office custody."
                    );
                }

                $equipment->decrement('quantity', $quantity);
            } elseif ($origin === EquipmentCase::ORIGIN_RETURN) {
                $return = ItemReturn::query()->lockForUpdate()->find($data['item_return_id'] ?? null);
                if (! $return || ! $return->equipment_id) {
                    throw new InvalidArgumentException('Select an unserviceable equipment return.');
                }

                if ($return->condition !== ReturnCondition::Damaged) {
                    throw new InvalidArgumentException('Only returns recorded as Unserviceable can be opened here.');
                }

                $equipment = Equipment::withTrashed()->findOrFail($return->equipment_id);
                $remaining = $this->remainingOnReturn($return);
                if ($remaining < 1) {
                    throw new InvalidArgumentException('Every unit of this return already has a case.');
                }

                $returnSerials = EquipmentSerialNumber::query()
                    ->where('item_return_id', $return->id)
                    ->where('status', EquipmentSerialNumber::STATUS_UNAVAILABLE)
                    ->lockForUpdate()
                    ->get();

                if ($returnSerials->isNotEmpty() || ! empty($data['serial_number_id'])) {
                    $serial = $returnSerials->firstWhere('id', (int) ($data['serial_number_id'] ?? 0));
                    if (! $serial) {
                        throw new InvalidArgumentException('Select one of the returned serial numbers awaiting assessment.');
                    }
                    $quantity = 1;
                }

                if ($quantity > $remaining) {
                    throw new InvalidArgumentException("Only {$remaining} unit(s) of this return are awaiting assessment.");
                }
            } else {
                throw new InvalidArgumentException('Unknown case origin.');
            }

            $warrantyActive = $equipment->hasActiveWarranty($reportedOn);

            $case = EquipmentCase::create([
                'case_number' => $this->nextCaseNumber(),
                'equipment_id' => $equipment->id,
                'equipment_serial_number_id' => $serial?->id,
                'item_return_id' => $return?->id,
                'origin' => $origin,
                'quantity' => $quantity,
                'status' => $status,
                'condition' => EquipmentStatus::CONDITION_UNSERVICEABLE,
                'custody' => EquipmentStatus::CUSTODY_SUPPLY_OFFICE,
                'reported_issue' => $data['reason'],
                'reported_on' => $reportedOn,
                'reported_by' => $user->id,
                'warranty_status' => $warrantyActive ? EquipmentStatus::WARRANTY_PENDING : null,
                'warranty_end_date_at_report' => $equipment->warranty_end_date,
                'disposal_approval_status' => $status === EquipmentStatus::FOR_DISPOSAL ? 'pending' : null,
            ]);

            $serial?->update(['status' => $status]);

            $this->event($case, $user, 'opened', null, $status, $data['reason'], $reportedOn, $data['reference'] ?? null, [
                'origin' => $origin,
                'quantity' => $quantity,
                'serial_number' => $serial?->serial_number,
                'item_return_id' => $return?->id,
            ]);

            if ($warrantyActive) {
                $this->event(
                    $case,
                    $user,
                    'warranty_review_required',
                    $status,
                    $status,
                    'Recorded warranty coverage is active (until '.$equipment->warranty_end_date->toDateString().'). Routed for supplier / warranty review before ordinary repair or disposal.',
                    $reportedOn,
                    $equipment->warranty_reference,
                );
            }

            return $case;
        });

        $this->log($user, 'Opened', $case, $case->status_label.' — '.$data['reason']);

        return $case->fresh(['equipment', 'serialNumber', 'events.performer']);
    }

    /**
     * Documented supplier / warranty decision. Required before ordinary repair or disposal
     * whenever the case was opened while coverage was active.
     */
    public function recordWarrantyDecision(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            if (! $case->isWarrantyPending()) {
                throw new InvalidArgumentException('This case has no pending warranty review.');
            }

            if (! array_key_exists($data['outcome'], EquipmentStatus::WARRANTY_OUTCOMES)) {
                throw new InvalidArgumentException('Select a valid warranty outcome.');
            }

            $case->update([
                'warranty_status' => EquipmentStatus::WARRANTY_DECIDED,
                'warranty_claim_reference' => $data['reference'],
                'warranty_outcome' => $data['outcome'],
                'warranty_decision_notes' => $data['reason'],
                'warranty_decided_on' => $data['event_date'],
                'warranty_decided_by' => $data['user_id'],
            ]);

            return ['warranty_decided', $case->status, ['outcome' => EquipmentStatus::WARRANTY_OUTCOMES[$data['outcome']]]];
        }, $data);
    }

    public function recordRepair(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            $this->assertStatus($case, [EquipmentStatus::FOR_REPAIR]);
            $this->assertWarrantyCleared($case);

            $case->update(['repair_outcome' => $data['reason']]);

            return ['repair_recorded', $case->status, []];
        }, $data);
    }

    public function recordServiceability(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            $this->assertStatus($case, [EquipmentStatus::FOR_REPAIR]);
            $this->assertWarrantyCleared($case);

            $supplierFixed = in_array($case->warranty_outcome, ['repaired_by_supplier', 'replaced_by_supplier'], true);
            if (! filled($case->repair_outcome) && ! $supplierFixed) {
                throw new InvalidArgumentException('Record the repair outcome before the serviceability check.');
            }

            $passed = (bool) $data['passed'];
            $case->update([
                'serviceability_passed' => $passed,
                'serviceability_checked_on' => $data['event_date'],
                'serviceability_checked_by' => $data['user_id'],
                'condition' => $passed ? EquipmentStatus::CONDITION_SERVICEABLE : EquipmentStatus::CONDITION_UNSERVICEABLE,
            ]);

            return [$passed ? 'serviceability_passed' : 'serviceability_failed', $case->status, ['passed' => $passed]];
        }, $data);
    }

    /**
     * Return a repaired unit to Available stock. Only after it passed the serviceability
     * check, is serviceable, and is still within its life span (clear for issuance).
     */
    public function returnToAvailable(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            $this->assertStatus($case, [EquipmentStatus::FOR_REPAIR]);
            $this->assertWarrantyCleared($case);

            if ($case->serviceability_passed !== true || $case->condition !== EquipmentStatus::CONDITION_SERVICEABLE) {
                throw new InvalidArgumentException('The unit must pass the serviceability check before it can return to Available.');
            }

            $asOf = Carbon::parse($data['event_date']);
            // The unit always goes back to the equipment record it belongs to: same internal ID,
            // property / inventory numbers and serial. No new record and no new number.
            $equipment = Equipment::withTrashed()->lockForUpdate()->findOrFail($case->equipment_id);
            $return = $case->item_return_id ? ItemReturn::query()->with('issuanceDetail')->find($case->item_return_id) : null;
            $acquired = $return?->issuanceDetail?->date_acquired;

            if ($equipment->trashed()) {
                throw new InvalidArgumentException('The equipment record was deleted. Restore it from Deleted Data first.');
            }

            if ($equipment->hasReachedLifespan($asOf, $acquired)) {
                throw new InvalidArgumentException('This unit has reached its life span and is not clear for issuance. Move it to disposal instead.');
            }

            $equipment->increment('quantity', $case->quantity);

            if ($case->equipment_serial_number_id) {
                EquipmentSerialNumber::query()->whereKey($case->equipment_serial_number_id)->update([
                    'status' => EquipmentSerialNumber::STATUS_IN_STOCK,
                    'issuance_detail_id' => null,
                ]);
            }

            $case->update([
                'status' => EquipmentStatus::RETURNED_TO_SERVICE,
                'condition' => EquipmentStatus::CONDITION_SERVICEABLE,
                'custody' => EquipmentStatus::CUSTODY_SUPPLY_OFFICE,
                'closed_at' => now(),
            ]);

            return ['returned_to_available', EquipmentStatus::RETURNED_TO_SERVICE, [
                'equipment_id' => $equipment->id,
                'property_number' => $case->serialNumber?->property_number ?: $equipment->property_number,
            ]];
        }, $data);
    }

    public function moveToDisposal(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            $this->assertStatus($case, [EquipmentStatus::FOR_REPAIR]);
            $this->assertWarrantyCleared($case);

            $case->update([
                'status' => EquipmentStatus::FOR_DISPOSAL,
                'condition' => EquipmentStatus::CONDITION_UNSERVICEABLE,
                'disposal_assessment' => $data['reason'],
                'disposal_approval_status' => 'pending',
            ]);

            return ['moved_to_disposal', EquipmentStatus::FOR_DISPOSAL, []];
        }, $data);
    }

    public function moveBackToRepair(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            $this->assertStatus($case, [EquipmentStatus::FOR_DISPOSAL]);

            if ($case->disposal_approval_status === 'approved') {
                throw new InvalidArgumentException('Disposal is already approved for this unit.');
            }

            $case->update([
                'status' => EquipmentStatus::FOR_REPAIR,
                'disposal_approval_status' => null,
                'serviceability_passed' => null,
            ]);

            return ['moved_back_to_repair', EquipmentStatus::FOR_REPAIR, []];
        }, $data);
    }

    public function recordDisposalAssessment(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            $this->assertStatus($case, [EquipmentStatus::FOR_DISPOSAL]);
            $this->assertWarrantyCleared($case);

            $case->update(['disposal_assessment' => $data['reason']]);

            return ['disposal_assessed', $case->status, []];
        }, $data);
    }

    public function recordDisposalApproval(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            $this->assertStatus($case, [EquipmentStatus::FOR_DISPOSAL]);
            $this->assertWarrantyCleared($case);

            if (! filled($case->disposal_assessment)) {
                throw new InvalidArgumentException('Record the disposal assessment before the approval.');
            }

            if (! in_array($data['approval_status'], ['approved', 'rejected'], true)) {
                throw new InvalidArgumentException('Approval status must be approved or rejected.');
            }

            $case->update([
                'disposal_approval_status' => $data['approval_status'],
                'disposal_approved_by_name' => $data['approved_by_name'],
                'disposal_approval_reference' => $data['reference'],
                'disposal_approval_date' => $data['event_date'],
            ]);

            return ['disposal_'.$data['approval_status'], $case->status, ['approved_by' => $data['approved_by_name']]];
        }, $data);
    }

    public function markDisposed(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            $this->assertStatus($case, [EquipmentStatus::FOR_DISPOSAL]);
            $this->assertWarrantyCleared($case);

            if ($case->disposal_approval_status !== 'approved') {
                throw new InvalidArgumentException('Disposal approval must be recorded as approved before the unit can be marked Disposed.');
            }

            if (! filled($data['reference'] ?? null)) {
                throw new InvalidArgumentException('Disposal reference is required.');
            }

            if ($case->disposal_approval_date && Carbon::parse($data['event_date'])->lt($case->disposal_approval_date)) {
                throw new InvalidArgumentException('Disposal date cannot be earlier than the approval date.');
            }

            $case->update([
                'status' => EquipmentStatus::DISPOSED,
                'disposal_date' => $data['event_date'],
                'disposal_reference' => $data['reference'],
                'disposal_method' => $data['method'] ?? null,
                'closed_at' => now(),
            ]);

            return ['disposed', EquipmentStatus::DISPOSED, ['method' => $data['method'] ?? null]];
        }, $data);
    }

    /**
     * IIRUP values supplied by Accounting and the inspection committee. Nothing is derived from
     * cost or useful life; blank stays blank. Status is not changed.
     */
    public function recordIirupValues(EquipmentCase $case, User $user, array $data): EquipmentCase
    {
        return $this->transition($case, $user, function (EquipmentCase $case, array $data) {
            $this->assertStatus($case, [EquipmentStatus::FOR_DISPOSAL, EquipmentStatus::DISPOSED]);

            $qty = fn (string $key) => isset($data[$key]) && $data[$key] !== '' ? (int) $data[$key] : null;
            $money = fn (string $key) => isset($data[$key]) && $data[$key] !== '' ? round((float) $data[$key], 2) : null;

            $sale = $qty('disposal_sale_qty');
            $transfer = $qty('disposal_transfer_qty');
            $destruction = $qty('disposal_destruction_qty');
            $others = $qty('disposal_others_qty');
            $specify = trim((string) ($data['disposal_others_specify'] ?? '')) ?: null;
            $orNumber = trim((string) ($data['or_number'] ?? '')) ?: null;
            $saleAmount = $money('sale_amount');

            $allocated = array_sum(array_filter([$sale, $transfer, $destruction, $others], fn ($v) => $v !== null));
            if ($allocated > (int) $case->quantity) {
                throw new InvalidArgumentException("Disposal quantities ({$allocated}) cannot exceed the reported quantity ({$case->quantity}).");
            }

            if (($others !== null && $others > 0) !== ($specify !== null)) {
                throw new InvalidArgumentException('"Others (Specify)" needs both a quantity and a description.');
            }

            if (($orNumber !== null || $saleAmount !== null) && ! $sale) {
                throw new InvalidArgumentException('An OR number or sale amount needs a sale quantity.');
            }

            $case->update([
                'unit_cost' => $money('unit_cost'),
                'accumulated_impairment' => $money('accumulated_impairment'),
                'carrying_amount' => $money('carrying_amount'),
                'disposal_sale_qty' => $sale,
                'disposal_transfer_qty' => $transfer,
                'disposal_destruction_qty' => $destruction,
                'disposal_others_qty' => $others,
                'disposal_others_specify' => $specify,
                'appraised_value' => $money('appraised_value'),
                'or_number' => $orNumber,
                'sale_amount' => $saleAmount,
            ]);

            $modes = array_filter([
                $sale ? "Sale {$sale}" : null,
                $transfer ? "Transfer {$transfer}" : null,
                $destruction ? "Destruction {$destruction}" : null,
                $others ? "Others {$others} ({$specify})" : null,
            ]);

            return ['iirup_values_recorded', $case->status, $modes ? ['disposal' => implode(', ', $modes)] : []];
        }, $data);
    }

    public function remainingOnReturn(ItemReturn $return): int
    {
        $inCases = (int) EquipmentCase::query()->where('item_return_id', $return->id)->sum('quantity');

        return max(0, (int) $return->quantity - $inCases);
    }

    /**
     * @param  callable(EquipmentCase, array): array{0: string, 1: string, 2: array}  $callback
     */
    private function transition(EquipmentCase $case, User $user, callable $callback, array $data): EquipmentCase
    {
        if (! filled($data['reason'] ?? null)) {
            throw new InvalidArgumentException('A reason / remarks entry is required for every transition.');
        }

        $date = Carbon::parse($data['event_date'] ?? now())->startOfDay();
        if ($date->gt(now()->endOfDay())) {
            throw new InvalidArgumentException('The transition date cannot be in the future.');
        }

        if ($date->lt($case->reported_on->copy()->startOfDay())) {
            throw new InvalidArgumentException('The transition date cannot be earlier than the date the issue was reported.');
        }

        $data['event_date'] = $date->toDateString();
        $data['user_id'] = $user->id;

        $result = DB::transaction(function () use ($case, $user, $callback, $data, $date) {
            $locked = EquipmentCase::query()->lockForUpdate()->findOrFail($case->id);
            $from = $locked->status;

            [$action, $to, $details] = $callback($locked, $data);

            if ($locked->equipment_serial_number_id && in_array($to, EquipmentStatus::RESTRICTED, true)) {
                EquipmentSerialNumber::query()->whereKey($locked->equipment_serial_number_id)->update(['status' => $to]);
            }

            $this->event($locked, $user, $action, $from, $to, $data['reason'], $date, $data['reference'] ?? null, $details);

            return [$locked, $action];
        });

        [$locked, $action] = $result;
        $this->log($user, ucfirst(str_replace('_', ' ', $action)), $locked, $data['reason']);

        return $locked->fresh(['equipment', 'serialNumber', 'events.performer']);
    }

    private function assertStatus(EquipmentCase $case, array $allowed): void
    {
        if (! in_array($case->status, $allowed, true)) {
            throw new InvalidArgumentException(
                'This action is not allowed while the unit is '.EquipmentStatus::label($case->status).'.'
            );
        }
    }

    private function assertWarrantyCleared(EquipmentCase $case): void
    {
        if ($case->isWarrantyPending()) {
            throw new InvalidArgumentException(
                'Warranty coverage was active when the issue was reported. Record the supplier / warranty decision (claim reference and outcome) before ordinary repair or disposal.'
            );
        }
    }

    private function event(
        EquipmentCase $case,
        User $user,
        string $action,
        ?string $from,
        ?string $to,
        string $reason,
        Carbon|string $date,
        ?string $reference = null,
        array $details = [],
    ): EquipmentCaseEvent {
        return $case->events()->create([
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'event_date' => Carbon::parse($date)->toDateString(),
            'reference' => filled($reference) ? trim((string) $reference) : null,
            'performed_by' => $user->id,
            'details' => array_filter($details, fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    private function nextCaseNumber(): string
    {
        $year = now()->year;
        $latest = EquipmentCase::withTrashed()
            ->where('case_number', 'like', "EQC-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('case_number')
            ->value('case_number');

        $sequence = $latest && preg_match('/-(\d+)$/', $latest, $matches) ? ((int) $matches[1]) + 1 : 1;

        return sprintf('EQC-%d-%04d', $year, $sequence);
    }

    private function log(User $user, string $action, EquipmentCase $case, string $note): void
    {
        $case->loadMissing('equipment');

        $this->activityLogService->log(
            $user,
            $action,
            'Equipment Status',
            sprintf(
                '%s %s (%s): %s',
                $case->case_number,
                $case->equipment?->name ?? 'equipment',
                EquipmentStatus::label($case->status),
                $note
            )
        );
    }
}

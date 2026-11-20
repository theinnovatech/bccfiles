<?php

namespace App\Http\Controllers;

use App\Enums\ReturnCondition;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentSerialNumber;
use App\Models\IssuanceDetail;
use App\Models\ItemReturn;
use App\Services\ActivityLogService;
use App\Services\EquipmentCaseService;
use App\Services\EquipmentStockService;
use App\Models\EquipmentCase;
use App\Support\EquipmentStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ItemReturnController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly EquipmentStockService $equipmentStock,
        private readonly EquipmentCaseService $caseService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->equipmentReturnRows());
    }

    public function returnedEquipments(): JsonResponse
    {
        return response()->json($this->equipmentReturnRows());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:255'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'issuance_detail_id' => ['nullable', 'exists:issuance_details,id'],
            'property_number' => ['nullable', 'string', 'max:255'],
            'inventory_number' => ['nullable', 'string', 'max:255'],
            'custom_equipment_name' => ['nullable', 'string', 'max:255'],
            'custom_property_number' => ['nullable', 'string', 'max:255'],
            'custom_inventory_number' => ['nullable', 'string', 'max:255'],
            'custom_equipment_type' => ['nullable', 'string', 'max:255'],
            'custom_equipment_category' => ['nullable', 'string', 'max:255'],
            'custom_date_issued' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:custom_date_acquired'],
            'custom_date_acquired' => ['nullable', 'date', 'before_or_equal:today'],
            'custom_specs' => ['nullable', 'string'],
            'custom_details' => ['nullable', 'string'],
            'department_id' => ['required', 'exists:departments,id'],
            'borrower_employee_id' => ['nullable', 'exists:employees,id'],
            'borrower_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'serial_number_ids' => ['nullable', 'array'],
            'serial_number_ids.*' => ['integer', 'exists:equipment_serial_numbers,id'],
            'reason' => ['nullable', 'string'],
            'condition' => ['required_without:next_status', 'nullable', Rule::enum(ReturnCondition::class)],
            'next_status' => ['nullable', Rule::in(['available', 'for_repair', 'unserviceable'])],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'date_returned' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'date_returned.required' => 'Returned Date is required before the return can be finalized.',
            'date_returned.before_or_equal' => 'Returned Date cannot be in the future.',
        ]);

        // Next status (as in the desktop app): Available = returned in good condition and back in
        // stock; For Repair = opens a repair case; Unserviceable = waits for assessment.
        $nextStatus = $data['next_status'] ?? (($data['condition'] ?? 'good') === 'good' ? 'available' : 'unserviceable');
        $data['condition'] = $nextStatus === 'available' ? ReturnCondition::Good->value : ReturnCondition::Damaged->value;
        $idempotencyKey = filled($data['idempotency_key'] ?? null) ? trim($data['idempotency_key']) : null;

        if ($idempotencyKey && ($existing = ItemReturn::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $this->duplicateReturn($existing);
        }

        $borrowerEmployeeId = $data['borrower_employee_id'] ?? null;
        $borrowerName = trim((string) ($data['borrower_name'] ?? ''));
        $equipmentId = $data['equipment_id'] ?? null;
        $customName = trim((string) ($data['custom_equipment_name'] ?? ''));

        if (empty($data['issuance_detail_id']) && ! $equipmentId && $customName === '') {
            return response()->json([
                'message' => 'Enter a property number from issued equipment, or use Custom Equipment for past data.',
            ], 422);
        }

        if (! $borrowerEmployeeId && $borrowerName === '') {
            return response()->json([
                'message' => 'Please select or type the name of the person returning the equipment.',
            ], 422);
        }

        if ($customName === '' && empty($data['issuance_detail_id'])) {
            return response()->json([
                'message' => 'Enter a property number from issued equipment. Available Supply Master stock cannot be returned here.',
            ], 422);
        }

        try {
            $return = DB::transaction(function () use ($data, $request, $borrowerEmployeeId, $borrowerName, $equipmentId, $customName, $nextStatus, $idempotencyKey) {
                if ($data['quantity'] < 1) {
                    throw new InvalidArgumentException('Return quantity must be at least 1.');
                }

                $condition = ReturnCondition::from($data['condition']);
                $dateReturned = Carbon::parse($data['date_returned']);
                $restocked = false;
                $detail = null;
                $reachedLifespan = false;
                $equipment = null;

                if (! empty($data['issuance_detail_id'])) {
                    // Locked so two simultaneous returns of the same line cannot both pass the
                    // outstanding-quantity check.
                    $detail = IssuanceDetail::query()
                        ->with(['equipment', 'issuance'])
                        ->lockForUpdate()
                        ->findOrFail($data['issuance_detail_id']);
                    $equipmentId = $detail->equipment_id;
                }

                // Revision 9: a return cannot be dated before the equipment was issued.
                $issuedOn = $detail?->issuance?->issued_date
                    ?? (filled($data['custom_date_issued'] ?? null) ? Carbon::parse($data['custom_date_issued']) : null);
                if ($issuedOn && $dateReturned->copy()->startOfDay()->lt($issuedOn->copy()->startOfDay())) {
                    throw new InvalidArgumentException(sprintf(
                        'Returned Date (%s) cannot be earlier than the issuance date (%s).',
                        $dateReturned->toDateString(),
                        $issuedOn->toDateString()
                    ));
                }

                if ($equipmentId) {
                    $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipmentId);
                    $acquired = $detail?->date_acquired ?: $equipment->date_acquired;
                    $reachedLifespan = $equipment->hasReachedLifespan($dateReturned, $acquired);

                    if ($detail) {
                        $alreadyReturned = ItemReturn::query()
                            ->where('issuance_detail_id', $detail->id)
                            ->sum('quantity');
                        $outstanding = (int) $detail->quantity - (int) $alreadyReturned;
                        if ($outstanding < 1) {
                            throw new InvalidArgumentException(
                                'This issuance has no outstanding quantity left to return.'
                            );
                        }
                        if ($data['quantity'] > $outstanding) {
                            throw new InvalidArgumentException(
                                "Only {$outstanding} outstanding unit(s) can be returned for this issuance."
                            );
                        }
                        if ($data['quantity'] < $outstanding && trim((string) ($data['reason'] ?? '')) === '') {
                            throw new InvalidArgumentException(
                                "Please enter the reason why only {$data['quantity']} of {$outstanding} outstanding unit(s) are being returned."
                            );
                        }
                    }
                }

                $referenceNumber = trim((string) ($data['reference_number'] ?? ''));

                $return = ItemReturn::create([
                    'reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
                    'idempotency_key' => $idempotencyKey,
                    'issuance_id' => $detail?->issuance_id,
                    'issuance_detail_id' => $detail?->id,
                    'equipment_id' => $equipmentId,
                    'custom_equipment_name' => $equipmentId ? null : $customName,
                    'custom_property_number' => $equipmentId ? null : ($data['custom_property_number'] ?? null),
                    'custom_inventory_number' => $equipmentId ? null : ($data['custom_inventory_number'] ?? null),
                    'custom_equipment_type' => $equipmentId ? null : ($data['custom_equipment_type'] ?? null),
                    'custom_equipment_category' => $equipmentId ? null : ($data['custom_equipment_category'] ?? null),
                    'custom_date_issued' => $equipmentId ? null : ($data['custom_date_issued'] ?? null),
                    'custom_date_acquired' => $equipmentId ? null : ($data['custom_date_acquired'] ?? null),
                    'custom_specs' => $equipmentId ? null : (filled($data['custom_specs'] ?? null) ? trim((string) $data['custom_specs']) : null),
                    'custom_details' => $equipmentId ? null : (filled($data['custom_details'] ?? null) ? trim((string) $data['custom_details']) : null),
                    'department_id' => $data['department_id'],
                    'borrower_employee_id' => $borrowerEmployeeId,
                    'borrower_name' => $borrowerEmployeeId ? null : $borrowerName,
                    'quantity' => $data['quantity'],
                    'reason' => $data['reason'] ?? null,
                    'condition' => $condition,
                    'restocked' => false,
                    'returned_by' => $request->user()->id,
                    'date_returned' => $dateReturned,
                ]);

                // Good returns go back into the equipment record the unit was issued from: same
                // record, same numbers and serials. No new record is created.
                if ($equipmentId && $condition === ReturnCondition::Good && ! $reachedLifespan) {
                    $this->equipmentStock->restoreToRecord($equipment, (int) $return->quantity);
                    $return->update(['restocked' => true]);
                    $restocked = true;
                }

                $units = $this->settleReturnedSerials($detail, $return, $data['serial_number_ids'] ?? [], $restocked);

                if ($nextStatus === 'for_repair' && $equipmentId) {
                    $this->openRepairCases($request, $return, $units, $data);
                }

                return $return->refresh();
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($idempotencyKey && ($existing = ItemReturn::query()->where('idempotency_key', $idempotencyKey)->first())) {
                return $this->duplicateReturn($existing);
            }

            throw $e;
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $return->load([
            'equipment.category',
            'department',
            'borrower',
            'returner',
            'issuanceDetail',
            'returnedSerialNumbers',
            'returnedStock.serialNumbers',
        ]);
        $return->equipment?->refresh();

        $equipmentLabel = $return->equipment?->name ?? $return->custom_equipment_name ?? 'custom equipment';
        $restockNote = match (true) {
            (bool) $return->restocked => ' and returned to Available on its original equipment record',
            $nextStatus === 'for_repair' => ' and sent For Repair',
            default => ' as Unserviceable',
        };
        $returnedStock = Equipment::query()->where('source_return_id', $return->id)->first();
        $asOf = $return->date_returned;
        $remainingLabel = $returnedStock?->formattedRemainingLifeSpan($asOf)
            ?? $return->equipment?->formattedRemainingLifeSpan(
                $asOf,
                $return->issuanceDetail?->date_acquired
            );
        $lifeSpanNote = $remainingLabel ? " Remaining life span: {$remainingLabel}." : '';

        $this->activityLogService->log(
            $request->user(),
            'Returned',
            'Returns',
            "Recorded return of {$return->quantity} unit(s) of {$equipmentLabel}{$restockNote}.{$lifeSpanNote}"
        );

        $return->setAttribute('serial_numbers', $this->visibleReturnSerials($return));

        return response()->json($return, 201);
    }

    /**
     * @return \Illuminate\Support\Collection<int, EquipmentSerialNumber>
     */
    private function settleReturnedSerials(
        ?IssuanceDetail $detail,
        ItemReturn $return,
        array $serialIds,
        bool $restocked
    ) {
        if (! $detail) {
            return collect();
        }

        $issued = $detail->serialNumbers()->issued()->lockForUpdate()->get();
        if ($issued->isEmpty()) {
            return collect();
        }

        $ids = collect($serialIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->count() !== (int) $return->quantity) {
            throw new InvalidArgumentException(
                "Select exactly {$return->quantity} serial number(s) being returned."
            );
        }

        $selected = $issued->whereIn('id', $ids->all());
        if ($selected->count() !== $ids->count()) {
            throw new InvalidArgumentException(
                'One or more selected serial numbers were not issued on this record.'
            );
        }

        foreach ($selected as $serial) {
            // The unit stays on its own equipment record (equipment_id unchanged).
            $serial->update([
                'issuance_detail_id' => null,
                'item_return_id' => $return->id,
                'status' => $restocked
                    ? EquipmentSerialNumber::STATUS_IN_STOCK
                    : EquipmentSerialNumber::STATUS_UNAVAILABLE,
            ]);
        }

        $return->update([
            'serial_numbers' => $selected
                ->map(fn ($serial) => [
                    'id' => $serial->id,
                    'serial_number' => $serial->serial_number,
                ])
                ->values()
                ->all(),
        ]);

        return $selected->values();
    }

    /**
     * "Return for repair": open a For Repair case for every returned unit, in the same
     * transaction as the return.
     */
    private function openRepairCases(Request $request, ItemReturn $return, $units, array $data): void
    {
        $base = [
            'origin' => EquipmentCase::ORIGIN_RETURN,
            'item_return_id' => $return->id,
            'status' => EquipmentStatus::FOR_REPAIR,
            'reason' => trim((string) ($data['reason'] ?? '')) ?: 'Returned for repair.',
            'event_date' => Carbon::parse($return->date_returned)->toDateString(),
            'reference' => $return->reference_number ?: 'Return #'.$return->id,
        ];

        if ($units->isEmpty()) {
            $this->caseService->open($request->user(), $base + ['quantity' => (int) $return->quantity]);

            return;
        }

        foreach ($units as $unit) {
            $this->caseService->open($request->user(), $base + ['serial_number_id' => $unit->id, 'quantity' => 1]);
        }
    }

    private function duplicateReturn(ItemReturn $return): JsonResponse
    {
        $return->load(['equipment.category', 'department', 'borrower', 'returner', 'issuanceDetail', 'returnedSerialNumbers']);
        $return->setAttribute('serial_numbers', $this->visibleReturnSerials($return));

        return response()->json(array_merge($return->toArray(), [
            'duplicate' => true,
            'message' => 'This return was already recorded. Nothing was processed again.',
        ]), 200);
    }

    private function equipmentReturnRows()
    {
        return $this->withVisibleSerials(
            ItemReturn::query()
                ->with([
                    'equipment.category',
                    'department',
                    'borrower',
                    'returner',
                    'issuanceDetail.issuance',
                    'returnedSerialNumbers',
                    'returnedStock.serialNumbers',
                ])
                ->where(function ($query) {
                    $query->whereNotNull('equipment_id')
                        ->orWhereNotNull('custom_equipment_name');
                })
                ->orderByDesc('id')
                ->get()
        );
    }

    private function withVisibleSerials($returns)
    {
        return $returns->map(function (ItemReturn $return) {
            $return->setAttribute('serial_numbers', $this->visibleReturnSerials($return));

            return $return;
        });
    }

    private function visibleReturnSerials(ItemReturn $return): array
    {
        $stored = collect($return->serial_numbers ?? [])
            ->map(fn ($serial) => [
                'id' => is_array($serial) ? ($serial['id'] ?? null) : $serial?->id,
                'serial_number' => is_array($serial)
                    ? ($serial['serial_number'] ?? '')
                    : (string) ($serial->serial_number ?? $serial),
            ])
            ->filter(fn ($serial) => trim((string) $serial['serial_number']) !== '')
            ->values();

        if ($stored->isNotEmpty()) {
            return $stored->all();
        }

        $linked = $return->returnedSerialNumbers
            ?: $return->returnedSerialNumbers()->get();

        if ($linked->isNotEmpty()) {
            return $linked
                ->map(fn ($serial) => [
                    'id' => $serial->id,
                    'serial_number' => $serial->serial_number,
                ])
                ->values()
                ->all();
        }

        $fromStock = ($return->returnedStock ?: $return->returnedStock()->with('serialNumbers')->get())
            ->flatMap(fn ($equipment) => $equipment->serialNumbers);

        return $fromStock
            ->map(fn ($serial) => [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
            ])
            ->values()
            ->all();
    }
}

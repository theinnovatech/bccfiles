<?php

namespace App\Services;

use App\Enums\ReturnCondition;
use App\Models\Equipment;
use App\Models\EquipmentCase;
use App\Models\EquipmentSerialNumber;
use App\Models\IssuanceDetail;
use App\Models\ItemReturn;
use App\Support\EquipmentStatus;
use Illuminate\Support\Collection;

/**
 * Read model for the Equipment Dashboard (Revision 1): unit counts per disposition and the
 * matching filtered lists. Counts are in units (listing quantities / issued quantities).
 */
class EquipmentStatusService
{
    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $caseTotals = EquipmentCase::query()
            ->selectRaw('status, SUM(quantity) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            EquipmentStatus::AVAILABLE => (int) Equipment::query()->sum('quantity'),
            EquipmentStatus::ISSUED => $this->issuedUnits(),
            EquipmentStatus::FOR_REPAIR => (int) ($caseTotals[EquipmentStatus::FOR_REPAIR] ?? 0),
            EquipmentStatus::FOR_DISPOSAL => (int) ($caseTotals[EquipmentStatus::FOR_DISPOSAL] ?? 0),
            EquipmentStatus::DISPOSED => (int) ($caseTotals[EquipmentStatus::DISPOSED] ?? 0),
            'warranty_active' => EquipmentCase::query()->warrantyActive()->count(),
            'awaiting_assessment' => $this->awaitingAssessmentUnits(),
        ];
    }

    public function issuedUnits(): int
    {
        $issued = (int) IssuanceDetail::query()
            ->whereNotNull('equipment_id')
            ->whereHas('issuance')
            ->sum('quantity');

        $returned = (int) ItemReturn::query()
            ->whereNotNull('issuance_detail_id')
            ->whereHas('issuanceDetail', fn ($query) => $query->whereNotNull('equipment_id')->whereHas('issuance'))
            ->sum('quantity');

        return max(0, $issued - $returned);
    }

    public function awaitingAssessmentUnits(): int
    {
        return (int) $this->unserviceableReturns()->sum('awaiting_quantity');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(string $status, array $filters = []): Collection
    {
        $rows = match ($status) {
            EquipmentStatus::AVAILABLE => $this->availableRows(),
            EquipmentStatus::ISSUED => $this->issuedRows(),
            EquipmentStatus::FOR_REPAIR, EquipmentStatus::FOR_DISPOSAL, EquipmentStatus::DISPOSED, EquipmentStatus::RETURNED_TO_SERVICE => $this->caseRows(
                EquipmentCase::query()->where('status', $status)
            ),
            'warranty' => $this->caseRows(EquipmentCase::query()->warrantyActive()),
            'awaiting_assessment' => $this->awaitingRows(),
            default => collect(),
        };

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $row['name'] ?? null,
                    $row['property_number'] ?? null,
                    $row['inventory_number'] ?? null,
                    $row['serial_number'] ?? null,
                    $row['case_number'] ?? null,
                    $row['holder'] ?? null,
                    $row['unit'] ?? null,
                    $row['category'] ?? null,
                ])));

                return str_contains($haystack, $search);
            });
        }

        return $rows->values();
    }

    private function availableRows(): Collection
    {
        return Equipment::query()
            ->with(['category', 'serialNumbers'])
            ->where('quantity', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn (Equipment $equipment) => [
                'row_type' => 'listing',
                'key' => 'listing-'.$equipment->id,
                'equipment_id' => $equipment->id,
                'name' => $equipment->name,
                'brand' => $equipment->brand,
                'model' => $equipment->model,
                'brand_model' => $equipment->brand_model,
                'category' => $equipment->category?->name,
                'property_number' => $equipment->property_number,
                'inventory_number' => $equipment->inventory_number,
                'property_card_number' => $equipment->property_card_number,
                'serial_number' => $equipment->inStockSerialNumbers()->pluck('serial_number')->implode(', ') ?: null,
                'serial_options' => $equipment->inStockSerialNumbers()
                    ->map(fn ($serial) => ['id' => $serial->id, 'serial_number' => $serial->serial_number, 'property_number' => $serial->property_number, 'inventory_number' => $serial->inventory_number])
                    ->values(),
                'quantity' => (int) $equipment->quantity,
                'status' => EquipmentStatus::AVAILABLE,
                'status_label' => EquipmentStatus::label(EquipmentStatus::AVAILABLE),
                'condition_label' => EquipmentStatus::conditionLabel(EquipmentStatus::CONDITION_SERVICEABLE),
                'custody_label' => EquipmentStatus::custodyLabel(EquipmentStatus::CUSTODY_SUPPLY_OFFICE),
                'origin' => $equipment->origin,
                'property_class_label' => $equipment->property_class_label,
                'date_acquired' => $equipment->date_acquired?->toDateString(),
                'warranty_end_date' => $equipment->warranty_end_date?->toDateString(),
                'warranty_covered' => $equipment->hasActiveWarranty(),
                'holder' => null,
                'unit' => null,
            ]);
    }

    private function issuedRows(): Collection
    {
        return IssuanceDetail::query()
            ->with(['equipment.category', 'issuance.receiver', 'issuance.department', 'serialNumbers'])
            ->whereNotNull('equipment_id')
            ->whereHas('issuance')
            ->withSum('returns as returned_qty', 'quantity')
            ->orderByDesc('id')
            ->get()
            ->map(function (IssuanceDetail $detail) {
                $outstanding = (int) $detail->quantity - (int) ($detail->returned_qty ?? 0);
                if ($outstanding < 1) {
                    return null;
                }

                $equipment = $detail->equipment;

                return [
                    'row_type' => 'issued',
                    'key' => 'issued-'.$detail->id,
                    'equipment_id' => $detail->equipment_id,
                    'issuance_id' => $detail->issuance_id,
                    'issuance_number' => $detail->issuance?->issuance_number,
                    'name' => $equipment?->name,
                    'brand' => $equipment?->brand,
                    'model' => $equipment?->model,
                    'brand_model' => $equipment?->brand_model,
                    'category' => $equipment?->category?->name,
                    'property_number' => $detail->property_number,
                    'inventory_number' => $detail->inventory_number,
                    'serial_number' => $detail->serialNumbers
                        ->where('status', EquipmentSerialNumber::STATUS_ISSUED)
                        ->pluck('serial_number')
                        ->implode(', ') ?: null,
                    'quantity' => $outstanding,
                    'status' => EquipmentStatus::ISSUED,
                    'status_label' => EquipmentStatus::label(EquipmentStatus::ISSUED),
                    'condition_label' => EquipmentStatus::conditionLabel(EquipmentStatus::CONDITION_SERVICEABLE),
                    'custody_label' => EquipmentStatus::custodyLabel(EquipmentStatus::CUSTODY_EMPLOYEE),
                    'holder' => $detail->issuance?->recipientDisplayName(),
                    'unit' => $detail->issuance?->departmentDisplayName(),
                    'date' => $detail->issuance?->issued_date?->toDateString(),
                    'date_acquired' => $detail->date_acquired?->toDateString(),
                ];
            })
            ->filter()
            ->values();
    }

    private function caseRows($query): Collection
    {
        return $query
            ->with(['equipment.category', 'serialNumber', 'itemReturn', 'events.performer', 'reporter'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (EquipmentCase $case) => $this->presentCase($case));
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCase(EquipmentCase $case): array
    {
        $equipment = $case->equipment;

        return [
            'row_type' => 'case',
            'key' => 'case-'.$case->id,
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'equipment_id' => $case->equipment_id,
            'name' => $equipment?->name,
            'brand' => $equipment?->brand,
            'model' => $equipment?->model,
            'brand_model' => $equipment?->brand_model,
            'category' => $equipment?->category?->name,
            'property_number' => $case->serialNumber?->property_number ?: $equipment?->property_number,
            'inventory_number' => $case->serialNumber?->inventory_number ?: $equipment?->inventory_number,
            'serial_number' => $case->serialNumber?->serial_number,
            'quantity' => (int) $case->quantity,
            'origin' => $case->origin,
            'origin_label' => $case->origin === EquipmentCase::ORIGIN_RETURN ? 'Unserviceable return' : 'Supply Office stock',
            'item_return_id' => $case->item_return_id,
            'return_reference' => $case->itemReturn?->reference_number,
            'status' => $case->status,
            'status_label' => $case->status_label,
            'condition' => $case->condition,
            'condition_label' => $case->condition_label,
            'custody_label' => $case->custody_label,
            'reported_issue' => $case->reported_issue,
            'reported_on' => $case->reported_on?->toDateString(),
            'reported_by' => $case->reporter?->name,
            'repair_outcome' => $case->repair_outcome,
            'serviceability_passed' => $case->serviceability_passed,
            'serviceability_checked_on' => $case->serviceability_checked_on?->toDateString(),
            'warranty_status' => $case->warranty_status,
            'warranty_active' => $case->warranty_active,
            'warranty_end_date' => $case->warranty_end_date_at_report?->toDateString(),
            'warranty_claim_reference' => $case->warranty_claim_reference,
            'warranty_outcome' => $case->warranty_outcome,
            'warranty_outcome_label' => $case->warranty_outcome ? (EquipmentStatus::WARRANTY_OUTCOMES[$case->warranty_outcome] ?? $case->warranty_outcome) : null,
            'disposal_assessment' => $case->disposal_assessment,
            'disposal_approval_status' => $case->disposal_approval_status,
            'disposal_approved_by_name' => $case->disposal_approved_by_name,
            'disposal_approval_reference' => $case->disposal_approval_reference,
            'disposal_approval_date' => $case->disposal_approval_date?->toDateString(),
            'disposal_method' => $case->disposal_method,
            'disposal_date' => $case->disposal_date?->toDateString(),
            'disposal_reference' => $case->disposal_reference,
            'property_class' => $equipment?->property_class,
            'property_class_label' => $equipment?->property_class_label,
            'equipment_unit_cost' => $equipment?->unit_cost,
            'iirup' => [
                'unit_cost' => $case->unit_cost,
                'accumulated_impairment' => $case->accumulated_impairment,
                'carrying_amount' => $case->carrying_amount,
                'disposal_sale_qty' => $case->disposal_sale_qty,
                'disposal_transfer_qty' => $case->disposal_transfer_qty,
                'disposal_destruction_qty' => $case->disposal_destruction_qty,
                'disposal_others_qty' => $case->disposal_others_qty,
                'disposal_others_specify' => $case->disposal_others_specify,
                'appraised_value' => $case->appraised_value,
                'or_number' => $case->or_number,
                'sale_amount' => $case->sale_amount,
            ],
            'date_acquired' => $equipment?->date_acquired?->toDateString(),
            'date' => $case->reported_on?->toDateString(),
            'closed_at' => $case->closed_at?->toIso8601String(),
            'holder' => null,
            'unit' => null,
            'events' => $case->events->map(fn ($event) => [
                'id' => $event->id,
                'action' => $event->action,
                'action_label' => ucfirst(str_replace('_', ' ', $event->action)),
                'from_status' => $event->from_status ? EquipmentStatus::label($event->from_status) : null,
                'to_status' => $event->to_status ? EquipmentStatus::label($event->to_status) : null,
                'reason' => $event->reason,
                'event_date' => $event->event_date?->toDateString(),
                'reference' => $event->reference,
                'performed_by' => $event->performer?->name,
                'details' => $event->details,
            ])->values(),
        ];
    }

    /**
     * Unserviceable returns with units not yet in a repair / disposal case.
     */
    public function unserviceableReturns(): Collection
    {
        $caseTotals = EquipmentCase::query()
            ->whereNotNull('item_return_id')
            ->selectRaw('item_return_id, SUM(quantity) as total')
            ->groupBy('item_return_id')
            ->pluck('total', 'item_return_id');

        return ItemReturn::query()
            ->with([
                'equipment' => fn ($query) => $query->withTrashed()->with('category'),
                'department',
                'borrower',
                'issuanceDetail',
                'returnedSerialNumbers',
            ])
            ->where('condition', ReturnCondition::Damaged->value)
            ->whereNotNull('equipment_id')
            ->orderByDesc('date_returned')
            ->get()
            ->map(function (ItemReturn $return) use ($caseTotals) {
                $return->setAttribute('awaiting_quantity', max(0, (int) $return->quantity - (int) ($caseTotals[$return->id] ?? 0)));

                return $return;
            })
            ->filter(fn (ItemReturn $return) => $return->awaiting_quantity > 0)
            ->values();
    }

    private function awaitingRows(): Collection
    {
        return $this->unserviceableReturns()->map(fn (ItemReturn $return) => [
            'row_type' => 'return',
            'key' => 'return-'.$return->id,
            'item_return_id' => $return->id,
            'equipment_id' => $return->equipment_id,
            'name' => $return->equipment?->name,
            'brand' => $return->equipment?->brand,
            'model' => $return->equipment?->model,
            'brand_model' => $return->equipment?->brand_model,
            'category' => $return->equipment?->category?->name,
            'property_number' => $return->issuanceDetail?->property_number ?? $return->equipment?->property_number,
            'inventory_number' => $return->issuanceDetail?->inventory_number ?? $return->equipment?->inventory_number,
            'serial_number' => $return->returnedSerialNumbers
                ->where('status', EquipmentSerialNumber::STATUS_UNAVAILABLE)
                ->pluck('serial_number')
                ->implode(', ') ?: null,
            'serial_options' => $return->returnedSerialNumbers
                ->where('status', EquipmentSerialNumber::STATUS_UNAVAILABLE)
                ->map(fn ($serial) => ['id' => $serial->id, 'serial_number' => $serial->serial_number])
                ->values(),
            'quantity' => (int) $return->awaiting_quantity,
            'status' => 'awaiting_assessment',
            'status_label' => 'Unserviceable',
            'condition_label' => EquipmentStatus::conditionLabel(EquipmentStatus::CONDITION_UNSERVICEABLE),
            'custody_label' => EquipmentStatus::custodyLabel(EquipmentStatus::CUSTODY_SUPPLY_OFFICE),
            'return_reference' => $return->reference_number,
            'holder' => $return->borrowerDisplayName(),
            'unit' => $return->department?->name,
            'date' => $return->date_returned?->toDateString(),
            'reason' => $return->reason,
            'warranty_covered' => $return->equipment?->hasActiveWarranty() ?? false,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\DeliveryInspection;
use App\Models\DeliveryInspectionLine;
use App\Models\Equipment;
use App\Models\InventoryPosting;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Revision 8: delivery inspection and posting of accepted quantities.
 *
 * Posting goes through the existing receiving transaction (InventoryService::receiveStock for
 * supplies, EquipmentStockService::receive for equipment). Each line is written to the
 * inventory_postings ledger under a unique key inside the same database transaction, and the
 * inspection row is locked while posting, so posting, retrying or re-reviewing the same
 * inspection can never add stock twice.
 */
class DeliveryInspectionService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly EquipmentStockService $equipmentStock,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function save(User $user, array $data, ?DeliveryInspection $inspection = null): DeliveryInspection
    {
        if ($inspection?->isPosted()) {
            throw new InvalidArgumentException('This inspection is already posted and can no longer be edited.');
        }

        $this->assertHeader($data);
        $lines = $this->normaliseLines($data['lines'] ?? []);

        $inspection = DB::transaction(function () use ($user, $data, $inspection, $lines) {
            $attributes = [
                'delivery_reference' => $this->clean($data['delivery_reference'] ?? null),
                'purchase_reference' => $this->clean($data['purchase_reference'] ?? null),
                'supplier' => $this->clean($data['supplier'] ?? null),
                'delivery_date' => $data['delivery_date'] ?? null,
                'inspection_date' => $data['inspection_date'],
                'inspector_name' => trim($data['inspector_name']),
                'inspected_by' => $data['inspected_by'] ?? $user->id,
                'remarks' => $this->clean($data['remarks'] ?? null),
            ];

            if ($inspection) {
                $inspection = DeliveryInspection::query()->lockForUpdate()->findOrFail($inspection->id);
                if ($inspection->isPosted()) {
                    throw new InvalidArgumentException('This inspection is already posted and can no longer be edited.');
                }
                $inspection->update($attributes);
                $inspection->lines()->delete();
            } else {
                $inspection = DeliveryInspection::create([
                    ...$attributes,
                    'inspection_number' => $this->nextNumber(),
                    'status' => DeliveryInspection::STATUS_DRAFT,
                    'created_by' => $user->id,
                ]);
            }

            foreach ($lines as $line) {
                $inspection->lines()->create($line);
            }

            return $inspection;
        });

        $this->activityLogService->log(
            $user,
            'Saved',
            'Delivery Inspection',
            "Saved inspection {$inspection->inspection_number} for ".$this->referenceLabel($inspection)
        );

        return $inspection->fresh(['lines.item', 'lines.equipment']);
    }

    /**
     * @return array{inspection: DeliveryInspection, already_posted: bool}
     */
    public function post(User $user, DeliveryInspection $inspection): array
    {
        try {
            $result = DB::transaction(function () use ($user, $inspection) {
                $locked = DeliveryInspection::query()->lockForUpdate()->findOrFail($inspection->id);

                if ($locked->isPosted()) {
                    return ['inspection' => $locked, 'already_posted' => true, 'posted' => []];
                }

                $lines = $locked->lines()->lockForUpdate()->get();
                if ($lines->isEmpty()) {
                    throw new InvalidArgumentException('Add at least one inspection line before posting.');
                }

                $posted = [];
                foreach ($lines as $line) {
                    $this->assertLine($line->toArray());

                    if ($line->posted_at || (int) $line->accepted_quantity < 1) {
                        continue;
                    }

                    $posting = InventoryPosting::create([
                        'posting_key' => InventoryPosting::keyForInspectionLine($line->id),
                        'delivery_inspection_id' => $locked->id,
                        'delivery_inspection_line_id' => $line->id,
                        'item_id' => $line->item_id,
                        'equipment_id' => $line->equipment_id,
                        'quantity' => (int) $line->accepted_quantity,
                        'posted_by' => $user->id,
                    ]);

                    $remarks = sprintf(
                        'Delivery inspection %s (%s): accepted %d of %d delivered',
                        $locked->inspection_number,
                        $this->referenceLabel($locked),
                        $line->accepted_quantity,
                        $line->delivered_quantity
                    );

                    if ($line->line_type === 'item') {
                        $item = Item::query()->find($line->item_id);
                        if (! $item) {
                            throw new InvalidArgumentException('An inspected supply item no longer exists. Restore it before posting.');
                        }
                        $movement = $this->inventoryService->receiveStock($item, (int) $line->accepted_quantity, $user, $remarks);
                        $posting->update(['stock_movement_id' => $movement->id]);
                        $posted[] = "{$line->accepted_quantity} x {$item->item_name}";
                    } else {
                        $equipment = Equipment::query()->find($line->equipment_id);
                        if (! $equipment) {
                            throw new InvalidArgumentException('An inspected equipment listing no longer exists. Restore it before posting.');
                        }
                        $this->equipmentStock->receive($equipment, (int) $line->accepted_quantity, $line->serial_numbers ?? []);
                        $posted[] = "{$line->accepted_quantity} x {$equipment->name}";
                    }

                    $line->update(['posted_at' => now()]);
                }

                $locked->update([
                    'status' => DeliveryInspection::STATUS_POSTED,
                    'posted_at' => now(),
                    'posted_by' => $user->id,
                ]);

                return ['inspection' => $locked, 'already_posted' => false, 'posted' => $posted];
            });
        } catch (UniqueConstraintViolationException) {
            // Another request posted the same line first; nothing was added by this request.
            return ['inspection' => $inspection->fresh(['lines.item', 'lines.equipment']), 'already_posted' => true];
        }

        if (! $result['already_posted']) {
            $this->activityLogService->log(
                $user,
                'Posted',
                'Delivery Inspection',
                "Posted inspection {$result['inspection']->inspection_number} to inventory: "
                    .($result['posted'] === [] ? 'no accepted quantity' : implode(', ', $result['posted']))
            );
        }

        return [
            'inspection' => $result['inspection']->fresh(['lines.item', 'lines.equipment', 'poster']),
            'already_posted' => $result['already_posted'],
        ];
    }

    private function assertHeader(array $data): void
    {
        if (! $this->clean($data['delivery_reference'] ?? null) && ! $this->clean($data['purchase_reference'] ?? null)) {
            throw new InvalidArgumentException('Link the inspection to a delivery receipt or purchase order reference.');
        }

        $inspectionDate = Carbon::parse($data['inspection_date'])->startOfDay();
        if (! empty($data['delivery_date']) && Carbon::parse($data['delivery_date'])->startOfDay()->gt($inspectionDate)) {
            throw new InvalidArgumentException('The inspection date cannot be earlier than the delivery date.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normaliseLines(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one inspection line.');
        }

        return array_map(function (array $line) {
            $line = [
                'line_type' => $line['line_type'],
                'item_id' => $line['line_type'] === 'item' ? (int) $line['item_id'] : null,
                'equipment_id' => $line['line_type'] === 'equipment' ? (int) $line['equipment_id'] : null,
                'description' => $this->clean($line['description'] ?? null),
                'expected_quantity' => (int) ($line['expected_quantity'] ?? 0),
                'delivered_quantity' => (int) ($line['delivered_quantity'] ?? 0),
                'accepted_quantity' => (int) ($line['accepted_quantity'] ?? 0),
                'rejected_quantity' => (int) ($line['rejected_quantity'] ?? 0),
                'condition' => $line['condition'] ?? 'good',
                'discrepancy_remarks' => $this->clean($line['discrepancy_remarks'] ?? null),
                'serial_numbers' => collect($line['serial_numbers'] ?? [])
                    ->map(fn ($serial) => trim((string) $serial))
                    ->filter()
                    ->values()
                    ->all() ?: null,
            ];

            $this->assertLine($line);

            return $line;
        }, array_values($lines));
    }

    private function assertLine(array $line): void
    {
        $label = $line['description'] ?? null;
        if (! $label) {
            $label = $line['line_type'] === 'item'
                ? (Item::withTrashed()->find($line['item_id'])?->item_name ?? 'item')
                : (Equipment::withTrashed()->find($line['equipment_id'])?->name ?? 'equipment');
        }

        $expected = (int) $line['expected_quantity'];
        $delivered = (int) $line['delivered_quantity'];
        $accepted = (int) $line['accepted_quantity'];
        $rejected = (int) $line['rejected_quantity'];

        if ($accepted + $rejected !== $delivered) {
            throw new InvalidArgumentException("{$label}: accepted ({$accepted}) + rejected ({$rejected}) must equal delivered ({$delivered}).");
        }

        if ($expected > 0 && $accepted > $expected) {
            throw new InvalidArgumentException("{$label}: accepted quantity ({$accepted}) cannot exceed the expected quantity ({$expected}).");
        }

        $discrepancy = $delivered !== $expected || $rejected > 0 || ($line['condition'] ?? 'good') !== 'good';
        if ($discrepancy && ! $this->clean($line['discrepancy_remarks'] ?? null)) {
            throw new InvalidArgumentException("{$label}: enter discrepancy remarks (short, over, damaged or rejected quantities).");
        }

        if (! array_key_exists($line['condition'] ?? '', DeliveryInspectionLine::CONDITIONS)) {
            throw new InvalidArgumentException("{$label}: select a valid condition.");
        }

        if ($line['line_type'] === 'equipment' && $accepted > 0) {
            $serials = $line['serial_numbers'] ?? [];
            $tracks = Equipment::withTrashed()->find($line['equipment_id'])?->serialNumbers()->exists();
            if ($tracks && count($serials) !== $accepted) {
                throw new InvalidArgumentException("{$label}: enter exactly {$accepted} serial number(s) for the accepted units.");
            }
        }
    }

    private function referenceLabel(DeliveryInspection $inspection): string
    {
        return collect([
            $inspection->delivery_reference ? "DR {$inspection->delivery_reference}" : null,
            $inspection->purchase_reference ? "PO {$inspection->purchase_reference}" : null,
        ])->filter()->implode(' / ') ?: 'no reference';
    }

    private function nextNumber(): string
    {
        $year = now()->year;
        $latest = DeliveryInspection::withTrashed()
            ->where('inspection_number', 'like', "DI-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('inspection_number')
            ->value('inspection_number');

        $sequence = $latest && preg_match('/-(\d+)$/', $latest, $matches) ? ((int) $matches[1]) + 1 : 1;

        return sprintf('DI-%d-%04d', $year, $sequence);
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function receiveStock(Item $item, int $quantity, User $user, ?string $remarks = null): StockMovement
    {
        $this->assertPositiveQuantity($quantity);

        return $this->mutateStock($item, $quantity, TransactionType::In, $user, $remarks);
    }

    public function issueStock(Item $item, int $quantity, User $user, ?string $remarks = null): StockMovement
    {
        $this->assertPositiveQuantity($quantity);

        return $this->mutateStock($item, -$quantity, TransactionType::Out, $user, $remarks);
    }

    public function returnStock(Item $item, int $quantity, User $user, ?string $remarks = null): StockMovement
    {
        $this->assertPositiveQuantity($quantity);

        return $this->mutateStock($item, $quantity, TransactionType::Return, $user, $remarks);
    }

    public function adjustStock(
        Item $item,
        int $delta,
        AdjustmentReason|string $reason,
        User $user,
        ?string $remarks = null
    ): StockMovement {
        if ($delta === 0) {
            throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
        }

        $reasonText = $reason instanceof AdjustmentReason ? $reason->value : $reason;
        $combinedRemarks = trim(($remarks ? $remarks.' ' : '').'Reason: '.$reasonText);

        return $this->mutateStock($item, $delta, TransactionType::Adjustment, $user, $combinedRemarks);
    }

    private function mutateStock(
        Item $item,
        int $delta,
        TransactionType $type,
        User $user,
        ?string $remarks = null
    ): StockMovement {
        return DB::transaction(function () use ($item, $delta, $type, $user, $remarks) {
            $lockedItem = Item::query()->lockForUpdate()->findOrFail($item->id);
            $previousStock = $lockedItem->current_stock;
            $newStock = $previousStock + $delta;

            if ($newStock < 0 && ! $this->allowNegativeStock()) {
                throw new InvalidArgumentException('Insufficient stock for this transaction.');
            }

            $lockedItem->update(['current_stock' => max(0, $newStock)]);
            $actualNewStock = max(0, $newStock);

            $movement = StockMovement::create([
                'item_id' => $lockedItem->id,
                'transaction_type' => $type->value,
                'quantity' => abs($delta),
                'previous_stock' => $previousStock,
                'new_stock' => $actualNewStock,
                'reference_number' => ReferenceNumberGenerator::forMovement(),
                'remarks' => $remarks,
                'performed_by' => $user->id,
            ]);

            $this->activityLogService->log(
                $user,
                $type->value,
                'Stock',
                sprintf(
                    '%s %d units of %s (%s). Stock: %d → %d',
                    $type->value,
                    abs($delta),
                    $lockedItem->item_name,
                    $lockedItem->barcode,
                    $previousStock,
                    $actualNewStock
                )
            );

            return $movement->load(['item', 'performer']);
        });
    }

    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }
    }

    private function allowNegativeStock(): bool
    {
        return Setting::getValue('allow_negative_stock', 'false') === 'true';
    }
}

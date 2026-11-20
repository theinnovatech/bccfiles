<?php

namespace App\Services;

use App\Models\Equipment;
use InvalidArgumentException;

/**
 * Equipment stock movements shared by returns, repair cases and delivery inspection.
 * All methods expect to run inside the caller's database transaction.
 */
class EquipmentStockService
{
    /**
     * Receive units into an existing listing (same rules as Stock Operations > Receive Equipment).
     *
     * @param  list<string>  $serialNumbers
     */
    public function receive(Equipment $equipment, int $quantity, array $serialNumbers = []): Equipment
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);
        $serials = collect($serialNumbers)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $tracksSerials = $equipment->serialNumbers()->exists();

        if ($tracksSerials && count($serials) !== $quantity) {
            throw new InvalidArgumentException(
                "Enter exactly {$quantity} serial number(s) for {$equipment->name}; this listing tracks serial numbers."
            );
        }

        $equipment->increment('quantity', $quantity);

        if ($serials !== []) {
            $equipment->appendInStockSerialNumbers($serials);
        }

        return $equipment->refresh();
    }

    /**
     * Put returned or repaired units back into the equipment record they belong to (same
     * internal ID, numbers and serials). Returns never create a new equipment record.
     */
    public function restoreToRecord(Equipment $equipment, int $quantity): Equipment
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        if ($equipment->trashed()) {
            throw new InvalidArgumentException('The equipment record was deleted. Restore it from Deleted Data first.');
        }

        $equipment->increment('quantity', $quantity);

        return $equipment->refresh();
    }
}

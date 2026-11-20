<?php

namespace App\Support;

use App\Models\Equipment;
use App\Models\Item;
use App\Models\OfficeFormDocument;
use Closure;

/**
 * Where each official number must be unique (feedback item 4), based on the rules already in
 * the database:
 *
 * - Equipment property number: one per equipment record (unique index on equipments).
 * - Equipment inventory number: shared sequence with supply inventory numbers, so unique across
 *   equipment records and supply items (the existing rule).
 * - Stock card number (Stock No.): one per supply item.
 * - Property card number: one per equipment record.
 * - ICS / Local RIV / IIRUP: one per document of that form type; every line of a document shares it.
 *
 * Issuance lines and unit (serial) records may repeat a property number: they record which
 * number a unit carried in each transaction, and the organization may share a reference across
 * the units of one line. Soft-deleted records still hold their numbers (the unique indexes
 * include them), so they are checked too. The record being edited is always excluded.
 */
final class NumberScopes
{
    public static function equipmentProperty(?int $ignoreEquipmentId = null): Closure
    {
        return fn (string $number) => Equipment::withTrashed()
            ->where('property_number', $number)
            ->when($ignoreEquipmentId, fn ($q) => $q->whereKeyNot($ignoreEquipmentId))
            ->exists();
    }

    public static function equipmentInventory(?int $ignoreEquipmentId = null, ?int $ignoreItemId = null): Closure
    {
        return fn (string $number) => Equipment::withTrashed()
            ->where('inventory_number', $number)
            ->when($ignoreEquipmentId, fn ($q) => $q->whereKeyNot($ignoreEquipmentId))
            ->exists()
            || Item::withTrashed()
                ->where('inventory_number', $number)
                ->when($ignoreItemId, fn ($q) => $q->whereKeyNot($ignoreItemId))
                ->exists();
    }

    public static function stockCard(?int $ignoreItemId = null): Closure
    {
        return fn (string $number) => Item::withTrashed()
            ->where('item_number', $number)
            ->when($ignoreItemId, fn ($q) => $q->whereKeyNot($ignoreItemId))
            ->exists();
    }

    public static function propertyCard(?int $ignoreEquipmentId = null): Closure
    {
        return fn (string $number) => Equipment::withTrashed()
            ->where('property_card_number', $number)
            ->when($ignoreEquipmentId, fn ($q) => $q->whereKeyNot($ignoreEquipmentId))
            ->exists();
    }

    public static function formDocument(string $formType): Closure
    {
        return fn (string $number) => OfficeFormDocument::query()
            ->where('form_type', $formType)
            ->where('document_number', $number)
            ->exists();
    }
}

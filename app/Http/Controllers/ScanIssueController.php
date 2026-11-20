<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Enums\TransactionType;
use App\Models\Equipment;
use App\Models\EquipmentSerialNumber;
use App\Models\IssuanceDetail;
use App\Models\Item;
use App\Models\StockMovement;
use App\Support\EquipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Revision 2: resolve a scanned QR / barcode (or a manually typed code) to a supply item
 * or an individual equipment unit, including whether it may be issued.
 * Confirmation goes through POST /issuances (the existing issuance workflow).
 */
class ScanIssueController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $code = trim($data['code']);

        if ($item = Item::query()->with(['category', 'unit', 'location'])->where(fn ($q) => $q
            ->where('barcode', $code)->orWhere('item_number', $code)->orWhere('inventory_number', $code))->first()) {
            return response()->json($this->presentItem($item, $code));
        }

        $serial = EquipmentSerialNumber::query()
            ->with(['equipment.category', 'equipment.serialNumbers', 'issuanceDetail.issuance'])
            ->where('serial_number', $code)
            ->orderByRaw("CASE WHEN status = 'in_stock' THEN 0 ELSE 1 END")
            ->first();

        if ($serial && $serial->equipment) {
            return response()->json($this->presentSerial($serial, $code));
        }

        if ($equipment = Equipment::query()->with(['category', 'serialNumbers'])->where(fn ($q) => $q
            ->where('barcode', $code)->orWhere('property_number', $code)->orWhere('inventory_number', $code))->first()) {
            return response()->json($this->presentListing($equipment, $code));
        }

        $issued = IssuanceDetail::query()
            ->with(['equipment', 'issuance'])
            ->whereNotNull('equipment_id')
            ->where('property_number', $code)
            ->withSum('returns as returned_qty', 'quantity')
            ->latest('id')
            ->first();

        if ($issued && $issued->equipment) {
            $outstanding = (int) $issued->quantity - (int) ($issued->returned_qty ?? 0);

            return response()->json([
                'code' => $code,
                'type' => 'equipment',
                'match' => 'issued_property_number',
                'available' => false,
                'reason' => $outstanding > 0
                    ? 'This unit is currently Issued to '.($issued->issuance?->recipientDisplayName() ?? 'an employee').' ('.($issued->issuance?->issuance_number ?? '').'). Record a return before issuing it again.'
                    : 'This property number belongs to an issuance that was already returned. Scan the returned stock listing instead.',
                'equipment' => $this->equipmentPayload($issued->equipment),
            ]);
        }

        return response()->json([
            'code' => $code,
            'message' => "No supply item or equipment unit matches \"{$code}\". Check the code or use manual lookup.",
        ], 404);
    }

    private function presentItem(Item $item, string $code): array
    {
        $reason = null;
        if ($item->status !== ItemStatus::Active) {
            $reason = "This item is {$item->status->value} and cannot be issued.";
        } elseif ((int) $item->current_stock < 1) {
            $reason = 'This item is out of stock.';
        }

        $lastReceived = StockMovement::query()
            ->where('item_id', $item->id)
            ->where('transaction_type', TransactionType::In->value)
            ->max('created_at');

        return [
            'code' => $code,
            'type' => 'item',
            'match' => 'item',
            'available' => $reason === null,
            'reason' => $reason,
            'item' => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'barcode' => $item->barcode,
                'item_number' => $item->item_number,
                'inventory_number' => $item->inventory_number,
                'brand' => $item->brand,
                'description' => $item->description,
                'category' => $item->category?->name,
                'unit' => $item->unit?->abbreviation ?? $item->unit?->name,
                'location' => $item->location?->name,
                'current_stock' => (int) $item->current_stock,
                'stock_status' => $item->stock_status,
                'stock_status_label' => $item->stock_status_label,
                'status' => $item->status?->value,
                'last_received_on' => $lastReceived ? substr((string) $lastReceived, 0, 10) : null,
            ],
        ];
    }

    private function presentSerial(EquipmentSerialNumber $serial, string $code): array
    {
        $equipment = $serial->equipment;
        $available = $serial->status === EquipmentSerialNumber::STATUS_IN_STOCK
            && $serial->issuance_detail_id === null
            && (int) $equipment->quantity > 0;

        $reason = null;
        if (! $available) {
            $reason = match ($serial->status) {
                EquipmentSerialNumber::STATUS_ISSUED => 'This unit is currently Issued to '
                    .($serial->issuanceDetail?->issuance?->recipientDisplayName() ?? 'an employee')
                    .' ('.($serial->issuanceDetail?->issuance?->issuance_number ?? '').').',
                EquipmentSerialNumber::STATUS_FOR_REPAIR => 'This unit is For Repair and cannot be issued.',
                EquipmentSerialNumber::STATUS_FOR_DISPOSAL => 'This unit is For Disposal and cannot be issued.',
                EquipmentSerialNumber::STATUS_DISPOSED => 'This unit is Disposed and cannot be issued.',
                EquipmentSerialNumber::STATUS_UNAVAILABLE => 'This unit is Unserviceable and cannot be issued.',
                default => 'This unit is not available for issuance.',
            };
        }

        return [
            'code' => $code,
            'type' => 'equipment',
            'match' => 'serial_number',
            'available' => $available,
            'reason' => $reason,
            'unit_status' => $serial->status,
            'unit_status_label' => EquipmentStatus::unitStatusLabel($serial->status),
            'serial' => ['id' => $serial->id, 'serial_number' => $serial->serial_number, 'property_number' => $serial->property_number, 'inventory_number' => $serial->inventory_number],
            'equipment' => $this->equipmentPayload($equipment),
        ];
    }

    private function presentListing(Equipment $equipment, string $code): array
    {
        $reason = (int) $equipment->quantity < 1
            ? 'No available unit of this equipment is in Supply Office custody (all issued, under repair, for disposal, or disposed).'
            : null;

        return [
            'code' => $code,
            'type' => 'equipment',
            'match' => 'listing',
            'available' => $reason === null,
            'reason' => $reason,
            'serial' => null,
            'equipment' => $this->equipmentPayload($equipment),
        ];
    }

    private function equipmentPayload(Equipment $equipment): array
    {
        $equipment->loadMissing(['category', 'serialNumbers']);

        return [
            'id' => $equipment->id,
            'name' => $equipment->name,
            'type' => $equipment->type,
            'brand' => $equipment->brand,
            'model' => $equipment->model,
            'brand_model' => $equipment->brand_model,
            'category' => $equipment->category?->name,
            'property_number' => $equipment->property_number,
            'inventory_number' => $equipment->inventory_number,
            'property_number_rule' => $equipment->propertyNumberRule(),
            'barcode' => $equipment->barcode,
            'specs' => $equipment->specs,
            'description' => $equipment->description,
            'quantity' => (int) $equipment->quantity,
            'origin' => $equipment->origin,
            'date_acquired' => $equipment->date_acquired?->toDateString(),
            'life_span' => $equipment->formattedRemainingLifeSpan(),
            'warranty_end_date' => $equipment->warranty_end_date?->toDateString(),
            'tracks_serials' => $equipment->serialNumbers->isNotEmpty(),
            'in_stock_serials' => $equipment->inStockSerialNumbers()
                ->map(fn ($serial) => ['id' => $serial->id, 'serial_number' => $serial->serial_number, 'property_number' => $serial->property_number, 'inventory_number' => $serial->inventory_number])
                ->values(),
        ];
    }
}

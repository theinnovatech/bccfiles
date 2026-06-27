<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
        ]);

        $item = Item::query()->where('barcode', $data['barcode'])->firstOrFail();
        $movement = $this->inventoryService->receiveStock($item, $data['quantity'], $request->user(), $data['remarks'] ?? null);

        return response()->json([
            'item' => $item->fresh()->load(['category', 'unit', 'location']),
            'movement' => $movement,
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string'],
            'actual_count' => ['required', 'integer', 'min:0'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'remarks' => ['nullable', 'string'],
        ]);

        $item = Item::query()->where('barcode', $data['barcode'])->firstOrFail();
        $delta = $data['actual_count'] - $item->current_stock;

        if ($delta === 0) {
            return response()->json(['message' => 'No adjustment needed. Stock matches actual count.'], 422);
        }

        $movement = $this->inventoryService->adjustStock(
            $item,
            $delta,
            AdjustmentReason::from($data['reason']),
            $request->user(),
            $data['remarks'] ?? null
        );

        return response()->json([
            'item' => $item->fresh()->load(['category', 'unit', 'location']),
            'movement' => $movement,
            'adjustment' => $delta,
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $query = \App\Models\StockMovement::query()
            ->with(['item', 'performer'])
            ->orderByDesc('created_at');

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->string('transaction_type'));
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->integer('item_id'));
        }

        return response()->json($query->paginate(25));
    }
}

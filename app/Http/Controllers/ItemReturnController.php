<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Services\ActivityLogService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemReturnController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ActivityLogService $activityLogService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            ItemReturn::query()
                ->with(['item', 'returner', 'issuance'])
                ->orderByDesc('date_returned')
                ->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
            'issuance_id' => ['nullable', 'exists:issuances,id'],
        ]);

        $item = Item::query()->where('barcode', $data['barcode'])->firstOrFail();

        $this->inventoryService->returnStock(
            $item,
            $data['quantity'],
            $request->user(),
            $data['reason'] ?? 'Return of unused items'
        );

        $return = ItemReturn::create([
            'issuance_id' => $data['issuance_id'] ?? null,
            'item_id' => $item->id,
            'quantity' => $data['quantity'],
            'reason' => $data['reason'] ?? null,
            'returned_by' => $request->user()->id,
            'date_returned' => now(),
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Returned',
            'Returns',
            "Returned {$data['quantity']} units of {$item->item_name}"
        );

        return response()->json($return->load(['item', 'returner']), 201);
    }
}

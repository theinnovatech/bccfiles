<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockCountItem;
use App\Models\StockCountSession;
use App\Services\ActivityLogService;
use App\Services\InventoryService;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockCountController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ActivityLogService $activityLogService
    ) {}

    public function storeSession(Request $request): JsonResponse
    {
        $session = StockCountSession::create([
            'session_number' => ReferenceNumberGenerator::forStockCount(),
            'started_by' => $request->user()->id,
            'status' => 'in_progress',
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Started',
            'Stock Count',
            "Started stock count session {$session->session_number}"
        );

        return response()->json($session->load('starter'), 201);
    }

    public function showSession(StockCountSession $session): JsonResponse
    {
        return response()->json($session->load(['starter', 'items.item']));
    }

    public function addItem(Request $request, StockCountSession $session): JsonResponse
    {
        if ($session->status !== 'in_progress') {
            return response()->json(['message' => 'Session is already completed.'], 422);
        }

        $data = $request->validate([
            'barcode' => ['required', 'string'],
            'physical_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $item = Item::query()->where('barcode', $data['barcode'])->firstOrFail();
        $expected = $item->current_stock;
        $physical = $data['physical_quantity'];
        $variance = $physical - $expected;
        $adjustmentCreated = false;

        if ($variance !== 0) {
            $this->inventoryService->adjustStock(
                $item,
                $variance,
                AdjustmentReason::Miscount,
                $request->user(),
                "Physical inventory session {$session->session_number}"
            );
            $adjustmentCreated = true;
        }

        $countItem = StockCountItem::updateOrCreate(
            ['session_id' => $session->id, 'item_id' => $item->id],
            [
                'expected_quantity' => $expected,
                'physical_quantity' => $physical,
                'variance' => $variance,
                'adjustment_created' => $adjustmentCreated,
            ]
        );

        return response()->json([
            'count_item' => $countItem->load('item'),
            'item' => $item->fresh(),
        ]);
    }

    public function complete(Request $request, StockCountSession $session): JsonResponse
    {
        if ($session->status !== 'in_progress') {
            return response()->json(['message' => 'Session is already completed.'], 422);
        }

        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Completed',
            'Stock Count',
            "Completed stock count session {$session->session_number}"
        );

        return response()->json($session->load(['starter', 'items.item']));
    }

    public function index(): JsonResponse
    {
        return response()->json(
            StockCountSession::query()
                ->with(['starter'])
                ->withCount('items')
                ->orderByDesc('created_at')
                ->paginate(15)
        );
    }
}

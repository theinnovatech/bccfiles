<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Issuance;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $itemsQuery = Item::query()->where('status', 'active');
        $movementsQuery = StockMovement::query()->whereDate('created_at', today());

        return response()->json([
            'total_items' => (clone $itemsQuery)->count(),
            'available_stocks' => (clone $itemsQuery)->sum('current_stock'),
            'low_stock_items' => (clone $itemsQuery)->whereColumn('current_stock', '<=', 'minimum_stock')->where('current_stock', '>', 0)->count(),
            'out_of_stock' => (clone $itemsQuery)->where('current_stock', 0)->count(),
            'todays_issuance' => (clone $movementsQuery)->where('transaction_type', TransactionType::Out->value)->sum('quantity'),
            'todays_received' => (clone $movementsQuery)->where('transaction_type', TransactionType::In->value)->sum('quantity'),
            'todays_issuance_records' => Issuance::query()->whereDate('issued_date', today())->count(),
            'registered_equipments' => Equipment::query()->count(),
        ]);
    }

    public function recentMovements(): JsonResponse
    {
        return response()->json(
            StockMovement::query()->with(['item', 'performer'])->orderByDesc('created_at')->limit(5)->get()
        );
    }

    public function recentIssuance(): JsonResponse
    {
        return response()->json(
            Issuance::query()->with(['department', 'issuer', 'details.item', 'details.equipment'])->orderByDesc('issued_date')->limit(5)->get()
        );
    }

    public function recentReturns(): JsonResponse
    {
        return response()->json(
            ItemReturn::query()
                ->with(['equipment', 'department', 'borrower', 'returner'])
                ->whereNotNull('equipment_id')
                ->orderByDesc('date_returned')
                ->limit(10)
                ->get()
        );
    }

    public function charts(): JsonResponse
    {
        $inventoryStatus = [
            'in_stock' => Item::query()->where('status', 'active')->whereColumn('current_stock', '>', 'minimum_stock')->count(),
            'low_stock' => Item::query()->where('status', 'active')->whereColumn('current_stock', '<=', 'minimum_stock')->where('current_stock', '>', 0)->count(),
            'out_of_stock' => Item::query()->where('status', 'active')->where('current_stock', 0)->count(),
        ];

        $monthlyIssuance = StockMovement::query()
            ->selectRaw('MONTH(created_at) as month, SUM(quantity) as total')
            ->where('transaction_type', TransactionType::Out->value)
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => ['month' => (int) $row->month, 'total' => (int) $row->total]);

        $lowStockItems = Item::query()
            ->with(['category', 'unit', 'location'])
            ->where('status', 'active')
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->orderBy('current_stock')
            ->limit(5)
            ->get();

        return response()->json([
            'inventory_status' => $inventoryStatus,
            'monthly_issuance' => $monthlyIssuance,
            'low_stock_items' => $lowStockItems,
        ]);
    }
}

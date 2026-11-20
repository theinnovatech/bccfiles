<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Issuance;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Models\StockMovement;
use App\Support\StockStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $itemsQuery = Item::query()->where('status', 'active');
        $movementsQuery = StockMovement::query()->whereDate('created_at', today());

        return response()->json([
            'total_items' => (clone $itemsQuery)->count(),
            'available_stocks' => (clone $itemsQuery)->sum('current_stock'),
            'low_stock_items' => (clone $itemsQuery)->withStockStatus(StockStatus::LOW)->count(),
            'out_of_stock' => (clone $itemsQuery)->withStockStatus(StockStatus::OUT)->count(),
            'reference_stock_missing' => (clone $itemsQuery)->where(function ($query) {
                $query->whereNull('reference_stock')->orWhere('reference_stock', '<', 1);
            })->count(),
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

    public function charts(Request $request): JsonResponse
    {
        $inventoryStatus = [
            'in_stock' => Item::query()->where('status', 'active')->withStockStatus(StockStatus::IN)->count(),
            'low_stock' => Item::query()->where('status', 'active')->withStockStatus(StockStatus::LOW)->count(),
            'out_of_stock' => Item::query()->where('status', 'active')->withStockStatus(StockStatus::OUT)->count(),
        ];

        $currentYear = (int) now()->year;
        $year = $request->integer('year') ?: $currentYear;
        $month = $request->filled('month') ? $request->integer('month') : null;

        if ($month !== null && ($month < 1 || $month > 12)) {
            $month = null;
        }

        $availableYears = StockMovement::query()
            ->where('transaction_type', TransactionType::Out->value)
            ->selectRaw('DISTINCT YEAR(created_at) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        if (! $availableYears->contains($currentYear)) {
            $availableYears = $availableYears->prepend($currentYear);
        }

        if (! $availableYears->contains($year)) {
            $availableYears = $availableYears->prepend($year);
        }

        $availableYears = $availableYears->unique()->sortDesc()->values();

        $totalsQuery = StockMovement::query()
            ->selectRaw('MONTH(created_at) as month, SUM(quantity) as total')
            ->where('transaction_type', TransactionType::Out->value)
            ->whereYear('created_at', $year);

        if ($month) {
            $totalsQuery->whereMonth('created_at', $month);
        }

        $totals = $totalsQuery
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->month => (int) $row->total]);

        $monthsToShow = $month ? [$month] : range(1, 12);
        $monthlyIssuance = collect($monthsToShow)->map(fn (int $monthNumber) => [
            'month' => $monthNumber,
            'month_name' => Carbon::createFromDate($year, $monthNumber, 1)->format('F'),
            'total' => $totals[$monthNumber] ?? 0,
        ])->values();

        $periodLabel = $month
            ? Carbon::createFromDate($year, $month, 1)->format('F Y')
            : 'January – December '.$year;

        $lowStockItems = Item::query()
            ->with(['category', 'unit', 'location'])
            ->where('status', 'active')
            ->withStockStatus('alerting')
            ->orderBy('current_stock')
            ->limit(5)
            ->get();

        return response()->json([
            'inventory_status' => $inventoryStatus,
            'monthly_issuance' => $monthlyIssuance,
            'monthly_issuance_year' => $year,
            'monthly_issuance_month' => $month,
            'monthly_issuance_years' => $availableYears,
            'monthly_issuance_period' => $periodLabel,
            'low_stock_items' => $lowStockItems,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemReturn;
use App\Models\IssuanceDetail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PredictiveInventoryService
{
    public const DEFAULT_WINDOW_DAYS = 90;

    public const MIN_DAYS_FOR_PREDICTION = 7;

    public const CRITICAL_DAYS = 7;

    public const WARNING_DAYS = 30;

    public function predictAll(int $windowDays = self::DEFAULT_WINDOW_DAYS): array
    {
        return Item::query()
            ->with(['category', 'unit'])
            ->orderBy('item_name')
            ->get()
            ->map(fn (Item $item) => $this->predictForItem($item, $windowDays))
            ->values()
            ->all();
    }

    public function predictForItem(Item $item, int $windowDays = self::DEFAULT_WINDOW_DAYS): array
    {
        $windowStart = now()->subDays($windowDays);
        $consumption = $this->consumptionSummary($item->id, $windowStart);
        $requesters = $this->requesterFrequency($item->id, $windowStart, $consumption['net_consumption']);

        $prediction = $this->buildPrediction($item, $consumption, $windowDays);

        return array_merge($prediction, [
            'item' => [
                'id'            => $item->id,
                'barcode'       => $item->barcode,
                'item_name'     => $item->item_name,
                'current_stock' => $item->current_stock,
                'minimum_stock' => $item->minimum_stock,
                'category'      => $item->category?->name,
                'unit'          => $item->unit?->abbreviation,
            ],
            'analysis_window_days' => $windowDays,
            'total_issued'         => $consumption['total_issued'],
            'total_returned'       => $consumption['total_returned'],
            'net_consumption'      => $consumption['net_consumption'],
            'issuance_events'      => $consumption['issuance_events'],
            'days_with_activity'   => $consumption['days_with_activity'],
            'top_requesters'       => $requesters->take(5)->values()->all(),
            'all_requesters'       => $requesters->values()->all(),
        ]);
    }

    private function consumptionSummary(int $itemId, Carbon $windowStart): array
    {
        $issuedStats = IssuanceDetail::query()
            ->where('item_id', $itemId)
            ->whereHas('issuance', fn ($query) => $query->where('issued_date', '>=', $windowStart))
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_issued, COUNT(*) as issuance_events')
            ->first();

        $totalIssued = (int) ($issuedStats->total_issued ?? 0);
        $issuanceEvents = (int) ($issuedStats->issuance_events ?? 0);

        $totalReturned = (int) ItemReturn::query()
            ->where('item_id', $itemId)
            ->where('date_returned', '>=', $windowStart)
            ->sum('quantity');

        $netConsumption = max(0, $totalIssued - $totalReturned);

        $firstActivity = IssuanceDetail::query()
            ->where('item_id', $itemId)
            ->whereHas('issuance', fn ($query) => $query->where('issued_date', '>=', $windowStart))
            ->join('issuances', 'issuance_details.issuance_id', '=', 'issuances.id')
            ->min('issuances.issued_date');

        $daysWithActivity = 0;

        if ($firstActivity) {
            $daysSinceFirst = (int) now()->diffInDays(Carbon::parse($firstActivity)) + 1;
            $daysWithActivity = max(self::MIN_DAYS_FOR_PREDICTION, min(now()->diffInDays($windowStart) + 1, $daysSinceFirst));
        }

        return [
            'total_issued'       => $totalIssued,
            'total_returned'     => $totalReturned,
            'net_consumption'    => $netConsumption,
            'issuance_events'    => $issuanceEvents,
            'days_with_activity' => $daysWithActivity,
        ];
    }

    private function requesterFrequency(int $itemId, Carbon $windowStart, int $netConsumption): Collection
    {
        if ($netConsumption === 0) {
            return collect();
        }

        return DB::table('issuance_details')
            ->join('issuances', 'issuance_details.issuance_id', '=', 'issuances.id')
            ->join('supply_requests', 'issuances.request_id', '=', 'supply_requests.id')
            ->join('users', 'supply_requests.requested_by', '=', 'users.id')
            ->where('issuance_details.item_id', $itemId)
            ->where('issuances.issued_date', '>=', $windowStart)
            ->select(
                'users.id',
                'users.name',
                DB::raw('SUM(issuance_details.quantity) as quantity_issued'),
                DB::raw('COUNT(*) as request_count')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('quantity_issued')
            ->get()
            ->map(function ($row) use ($netConsumption) {
                $quantity = (int) $row->quantity_issued;

                return [
                    'id'               => (int) $row->id,
                    'name'             => $row->name,
                    'quantity_issued'  => $quantity,
                    'request_count'    => (int) $row->request_count,
                    'share_percent'    => round(($quantity / $netConsumption) * 100, 1),
                ];
            });
    }

    private function buildPrediction(Item $item, array $consumption, int $windowDays): array
    {
        if ($item->current_stock === 0) {
            return [
                'status'                 => 'out_of_stock',
                'status_label'           => 'Out of stock',
                'daily_consumption_rate' => null,
                'weekly_consumption_rate'=> null,
                'estimated_days_left'    => 0,
                'projected_stockout_date'=> now()->toDateString(),
                'confidence'             => 'high',
                'message'                => 'This item has no stock left.',
            ];
        }

        if ($consumption['issuance_events'] === 0 || $consumption['days_with_activity'] === 0) {
            return [
                'status'                 => 'no_data',
                'status_label'           => 'No records yet',
                'daily_consumption_rate' => null,
                'weekly_consumption_rate'=> null,
                'estimated_days_left'    => null,
                'projected_stockout_date'=> null,
                'confidence'             => 'low',
                'message'                => 'This item has not been issued recently, so we cannot estimate how long stock will last.',
            ];
        }

        if ($consumption['net_consumption'] === 0) {
            return [
                'status'                 => 'stable',
                'status_label'           => 'Enough stock for now',
                'daily_consumption_rate' => 0,
                'weekly_consumption_rate'=> 0,
                'estimated_days_left'    => null,
                'projected_stockout_date'=> null,
                'confidence'             => $this->confidenceLevel($consumption),
                'message'                => 'Very little has been taken out lately. Stock should last a long time.',
            ];
        }

        $dailyRate = $consumption['net_consumption'] / $consumption['days_with_activity'];
        $weeklyRate = $dailyRate * 7;
        $daysLeft = (int) floor($item->current_stock / $dailyRate);
        $stockoutDate = now()->addDays($daysLeft)->toDateString();

        $status = 'healthy';
        $statusLabel = 'Enough stock for now';
        $message = "Based on recent requests, stock may last about {$daysLeft} day(s).";

        if ($daysLeft <= self::CRITICAL_DAYS) {
            $status = 'critical';
            $statusLabel = 'Running out soon';
            $message = 'Stock may run out within a week. Please reorder soon.';
        } elseif ($daysLeft <= self::WARNING_DAYS) {
            $status = 'warning';
            $statusLabel = 'Running low';
            $message = 'Stock may run out within about a month. Consider ordering more.';
        } elseif ($item->isLowStock()) {
            $status = 'warning';
            $statusLabel = 'Below minimum';
            $message = 'Stock is below the minimum level. Please check and reorder if needed.';
        }

        return [
            'status'                  => $status,
            'status_label'            => $statusLabel,
            'daily_consumption_rate'  => round($dailyRate, 2),
            'weekly_consumption_rate' => round($weeklyRate, 2),
            'estimated_days_left'     => $daysLeft,
            'projected_stockout_date' => $stockoutDate,
            'confidence'              => $this->confidenceLevel($consumption),
            'message'                 => $message,
        ];
    }

    private function confidenceLevel(array $consumption): string
    {
        if ($consumption['issuance_events'] >= 10 && $consumption['days_with_activity'] >= 30) {
            return 'high';
        }

        if ($consumption['issuance_events'] >= 3 && $consumption['days_with_activity'] >= self::MIN_DAYS_FOR_PREDICTION) {
            return 'medium';
        }

        return 'low';
    }
}

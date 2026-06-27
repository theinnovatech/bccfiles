<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\PredictiveInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictiveInventoryController extends Controller
{
    public function __construct(private readonly PredictiveInventoryService $predictiveInventoryService) {}

    public function index(Request $request): JsonResponse
    {
        $windowDays = (int) $request->integer('window_days', PredictiveInventoryService::DEFAULT_WINDOW_DAYS);
        $windowDays = max(7, min(365, $windowDays));

        $data = $this->predictiveInventoryService->predictAll($windowDays);

        return response()->json([
            'window_days' => $windowDays,
            'summary'     => $this->summarize($data),
            'data'        => $data,
        ]);
    }

    public function show(Request $request, Item $item): JsonResponse
    {
        $windowDays = (int) $request->integer('window_days', PredictiveInventoryService::DEFAULT_WINDOW_DAYS);
        $windowDays = max(7, min(365, $windowDays));

        return response()->json($this->predictiveInventoryService->predictForItem($item, $windowDays));
    }

    private function summarize(array $predictions): array
    {
        $counts = [
            'total'        => count($predictions),
            'critical'     => 0,
            'warning'      => 0,
            'out_of_stock' => 0,
            'stable'       => 0,
            'no_data'      => 0,
            'healthy'      => 0,
        ];

        foreach ($predictions as $row) {
            match ($row['status']) {
                'critical'     => $counts['critical']++,
                'warning'      => $counts['warning']++,
                'out_of_stock' => $counts['out_of_stock']++,
                'stable'       => $counts['stable']++,
                'no_data'      => $counts['no_data']++,
                default        => $counts['healthy']++,
            };
        }

        return $counts;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ObimsNotification;
use App\Services\LowStockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function __construct(
        private readonly LowStockAlertService $lowStockAlertService
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Continuously re-check stock while staff use the app (at most once per minute).
        Cache::remember('obims:low-stock-scan', 60, function () {
            $this->lowStockAlertService->scanAllActiveItems();

            return true;
        });

        $notifications = ObimsNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $unreadCount = ObimsNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, ObimsNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $notification->markAsRead();

        return response()->json(['notification' => $notification->fresh()]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        ObimsNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}

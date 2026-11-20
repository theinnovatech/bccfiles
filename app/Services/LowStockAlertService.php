<?php

namespace App\Services;

use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\Item;
use App\Models\ObimsNotification;
use App\Models\User;
use App\Support\StockStatus;
use Illuminate\Support\Facades\DB;

/**
 * Low / out-of-stock alerts (feedback item 5).
 *
 * The alert state is stored on the item (stock_alert_level, stock_alert_since), not inferred
 * from unread notifications. Staff are notified once when an item becomes Low or Out of Stock
 * (and again only if it gets worse, Low -> Out). Reading a notification or refreshing a page
 * never creates another one. When stock rises above the threshold, the alert is resolved: the
 * state is cleared and the open notifications for the item are marked read.
 *
 * Called after every stock change (issuance, accepted deliveries, returns, adjustments),
 * after reference / percentage edits, and by the scheduled scan.
 */
class LowStockAlertService
{
    private const LEVEL_LOW = 'low';
    private const LEVEL_OUT = 'out';

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function evaluateAfterStockChange(Item $item, int $previousStock, int $newStock): void
    {
        $fresh = Item::query()->find($item->id);
        if ($fresh) {
            $this->sync($fresh);
        }
    }

    public function evaluateItem(Item $item): void
    {
        $this->sync($item->fresh() ?? $item);
    }

    /**
     * @return int Number of items that raised a new alert
     */
    public function scanAllActiveItems(): int
    {
        $raised = 0;

        // Items whose stored state may be out of date: currently alerting, or still holding a state.
        Item::query()
            ->where(function ($query) {
                StockStatus::apply($query->where('status', ItemStatus::Active), 'alerting');
            })
            ->orWhereNotNull('stock_alert_level')
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$raised) {
                foreach ($items as $item) {
                    $raised += $this->sync($item) ? 1 : 0;
                }
            });

        return $raised;
    }

    /**
     * Bring the stored alert state in line with the current status.
     *
     * @return bool Whether a new notification was sent
     */
    public function sync(Item $item): bool
    {
        $level = $item->status === ItemStatus::Active
            ? match ($item->stockStatus()) {
                StockStatus::OUT => self::LEVEL_OUT,
                StockStatus::LOW => self::LEVEL_LOW,
                default => null,
            }
            : null;

        $stored = $item->stock_alert_level;

        if ($level === $stored) {
            return false;
        }

        if ($level === null) {
            $this->resolve($item);

            return false;
        }

        // New alert, or escalation Low -> Out. Recovery Out -> Low only updates the state.
        $notify = $stored === null || ($stored === self::LEVEL_LOW && $level === self::LEVEL_OUT);

        $updated = Item::query()
            ->whereKey($item->id)
            ->where(fn ($q) => $stored === null ? $q->whereNull('stock_alert_level') : $q->where('stock_alert_level', $stored))
            ->update([
                'stock_alert_level' => $level,
                'stock_alert_since' => $stored === null ? now() : DB::raw('stock_alert_since'),
            ]);

        // Another request changed the state first: it sends the notification.
        if ($updated === 0) {
            return false;
        }

        $item->stock_alert_level = $level;

        if (! $notify) {
            return false;
        }

        $this->notify($item, $level);

        return true;
    }

    private function resolve(Item $item): void
    {
        Item::query()->whereKey($item->id)->update([
            'stock_alert_level' => null,
            'stock_alert_since' => null,
        ]);
        $item->stock_alert_level = null;

        ObimsNotification::query()
            ->whereNull('read_at')
            ->whereIn('type', ['low_stock', 'out_of_stock'])
            ->where('data->item_id', $item->id)
            ->update(['read_at' => now()]);
    }

    private function notify(Item $item, string $level): void
    {
        $type = $level === self::LEVEL_OUT ? 'out_of_stock' : 'low_stock';

        if ($type === 'out_of_stock') {
            ObimsNotification::query()
                ->whereNull('read_at')
                ->where('type', 'low_stock')
                ->where('data->item_id', $item->id)
                ->update(['read_at' => now()]);
        }

        $stock = (int) $item->current_stock;
        $threshold = $item->low_stock_threshold;
        $percent = $item->effective_low_stock_percent;
        $code = $item->barcode ?: $item->item_number;
        $codeLabel = $code ? " ({$code})" : '';
        $basis = $item->reference_stock
            ? sprintf('reference %s × %s%% = threshold %s', number_format($item->reference_stock), rtrim(rtrim(number_format((float) $percent, 2), '0'), '.'), rtrim(rtrim(number_format($threshold, 2), '0'), '.'))
            : sprintf('reference stock not configured; minimum %s', number_format((int) $item->minimum_stock));

        if ($type === 'out_of_stock') {
            $title = 'Out of stock';
            $message = "{$item->item_name}{$codeLabel} is out of stock (0 remaining; {$basis}).";
            $url = '/items?stockStatus=out_of_stock';
        } else {
            $title = 'Low stock alert';
            $message = "{$item->item_name}{$codeLabel} is low: {$stock} remaining, at or below the threshold ({$basis}).";
            $url = '/items?stockStatus=low_stock';
        }

        $data = [
            'item_id' => $item->id,
            'barcode' => $item->barcode,
            'item_number' => $item->item_number,
            'item_name' => $item->item_name,
            'current_stock' => $stock,
            'reference_stock' => $item->reference_stock,
            'low_stock_percent' => $percent,
            'low_stock_threshold' => $threshold,
            'minimum_stock' => (int) $item->minimum_stock,
            'url' => $url,
        ];

        foreach ($this->staffRecipients() as $user) {
            $this->notificationService->send($user, $type, $title, $message, $data);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function staffRecipients()
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::SupplyOfficer])
            ->where('is_active', true)
            ->get();
    }
}

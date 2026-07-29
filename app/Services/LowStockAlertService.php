<?php

namespace App\Services;

use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\Item;
use App\Models\ObimsNotification;
use App\Models\User;

class LowStockAlertService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Edge-triggered alert after a stock mutation (notify only when level worsens).
     */
    public function evaluateAfterStockChange(Item $item, int $previousStock, int $newStock): void
    {
        $minimum = (int) $item->minimum_stock;
        $previousLevel = $this->stockLevel($previousStock, $minimum);
        $newLevel = $this->stockLevel($newStock, $minimum);

        if ($newLevel === 'ok') {
            if ($previousLevel !== 'ok') {
                $this->clearOpenAlerts($item->id);
            }

            return;
        }

        if ($newLevel === $previousLevel) {
            return;
        }

        $item->current_stock = $newStock;
        $this->notifyForItem($item, $newLevel);
    }

    /**
     * Full check for one item (used after minimum_stock edits and scheduled scans).
     */
    public function evaluateItem(Item $item): void
    {
        if ($item->status !== ItemStatus::Active) {
            return;
        }

        $level = $this->stockLevel((int) $item->current_stock, (int) $item->minimum_stock);

        if ($level === 'ok') {
            $this->clearOpenAlerts($item->id);

            return;
        }

        $this->notifyForItem($item, $level);
    }

    /**
     * Scan all active items and notify staff about low / out-of-stock levels.
     *
     * @return int Number of items that triggered a new alert episode
     */
    public function scanAllActiveItems(): int
    {
        $alerted = 0;

        Item::query()
            ->where('status', ItemStatus::Active)
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$alerted) {
                foreach ($items as $item) {
                    $level = $this->stockLevel((int) $item->current_stock, (int) $item->minimum_stock);

                    if ($level === 'ok') {
                        continue;
                    }

                    $type = $level === 'out' ? 'out_of_stock' : 'low_stock';

                    if ($this->hasOpenAlert($item->id, $type)) {
                        continue;
                    }

                    $this->notifyForItem($item, $level);
                    $alerted++;
                }
            });

        return $alerted;
    }

    private function notifyForItem(Item $item, string $level): void
    {
        $type = $level === 'out' ? 'out_of_stock' : 'low_stock';

        if ($this->hasOpenAlert($item->id, $type)) {
            return;
        }

        if ($type === 'out_of_stock') {
            ObimsNotification::query()
                ->whereNull('read_at')
                ->where('type', 'low_stock')
                ->where('data->item_id', $item->id)
                ->update(['read_at' => now()]);
        }

        $stock = (int) $item->current_stock;
        $minimum = (int) $item->minimum_stock;
        $code = $item->barcode ?: $item->item_number;
        $codeLabel = $code ? " ({$code})" : '';

        if ($type === 'out_of_stock') {
            $title = 'Out of stock';
            $message = "{$item->item_name}{$codeLabel} is out of stock (0 on hand; minimum {$minimum}).";
            $url = '/items?stockStatus=out_of_stock';
        } else {
            $title = 'Low stock alert';
            $message = "{$item->item_name}{$codeLabel} is low: {$stock} on hand (minimum {$minimum}).";
            $url = '/items?stockStatus=low_stock';
        }

        $data = [
            'item_id' => $item->id,
            'barcode' => $item->barcode,
            'item_number' => $item->item_number,
            'item_name' => $item->item_name,
            'current_stock' => $stock,
            'minimum_stock' => $minimum,
            'url' => $url,
        ];

        foreach ($this->staffRecipients() as $user) {
            $this->notificationService->send($user, $type, $title, $message, $data);
        }
    }

    private function stockLevel(int $stock, int $minimum): string
    {
        if ($stock <= 0) {
            return 'out';
        }

        if ($stock <= $minimum) {
            return 'low';
        }

        return 'ok';
    }

    private function hasOpenAlert(int $itemId, string $type): bool
    {
        return ObimsNotification::query()
            ->whereNull('read_at')
            ->where('type', $type)
            ->where('data->item_id', $itemId)
            ->exists();
    }

    private function clearOpenAlerts(int $itemId): void
    {
        ObimsNotification::query()
            ->whereNull('read_at')
            ->whereIn('type', ['low_stock', 'out_of_stock'])
            ->where('data->item_id', $itemId)
            ->update(['read_at' => now()]);
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

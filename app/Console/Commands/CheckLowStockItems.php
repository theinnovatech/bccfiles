<?php

namespace App\Console\Commands;

use App\Services\LowStockAlertService;
use Illuminate\Console\Command;

class CheckLowStockItems extends Command
{
    protected $signature = 'stock:check-low';

    protected $description = 'Scan inventory and notify staff about low or out-of-stock items';

    public function handle(LowStockAlertService $lowStockAlertService): int
    {
        $alerted = $lowStockAlertService->scanAllActiveItems();

        $this->info("Low-stock scan complete. New alerts for {$alerted} item(s).");

        return self::SUCCESS;
    }
}

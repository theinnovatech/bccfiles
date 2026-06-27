<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const DEFAULT_ORGANIZATION_NAME = 'DepEd Supply Unit Inventory';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('services.turnstile.use_test_keys')) {
            config([
                'services.turnstile.site_key' => '1x00000000000000000000AA',
                'services.turnstile.secret_key' => '1x0000000000000000000000000000000AA',
            ]);
        }

        View::composer('*', function ($view) {
            $view->with(
                'organizationName',
                Setting::getValue('organization_name', self::DEFAULT_ORGANIZATION_NAME)
            );
        });
    }
}

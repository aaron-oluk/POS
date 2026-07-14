<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        // Formats a USD-denominated amount as the store's active currency,
        // e.g. @money($product->price) -> "USh 12,950".
        Blade::directive('money', function (string $expression) {
            return "<?php echo \App\Models\Setting::current()->money({$expression}); ?>";
        });

        // Formats a UTC-stored timestamp in the store's configured timezone,
        // e.g. @localTime($order->created_at, 'M j, g:i A').
        Blade::directive('localTime', function (string $expression) {
            return "<?php echo \App\Models\Setting::current()->localTime({$expression}); ?>";
        });
    }
}

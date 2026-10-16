<?php

namespace App\Providers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Observers\VendorOrderObserver;
use App\Modules\Order\Models\VendorOrder;
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
        VendorOrder::observe(VendorOrderObserver::class);

        // Order::observe(OrderObserver::class);
    }
}

<?php

namespace App\Jobs;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\VendorOrderSplitterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SplitOrderIntoVendorOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function handle(VendorOrderSplitterService $splitterService): void
    {
        // الـ Job هنا أصبح مجرد "موصلطلبات" (Messenger) للخدمة
        $splitterService->split($this->order);
    }
}
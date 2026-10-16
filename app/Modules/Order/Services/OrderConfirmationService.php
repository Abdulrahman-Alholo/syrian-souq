<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\Order;
use App\Jobs\SplitOrderIntoVendorOrdersJob;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderConfirmationService
{
    /**
     * تأكيد الطلب وتجهيزه للإرسال للتجار
     */
    public function confirm(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            
            // 1. حماية التزامن (Concurrency Protection)
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            // 2. حماية تكرار الحالة (Idempotency)
            if ($lockedOrder->status !== 'pending_conf') { // تأكد من مطابقة اسم الحالة لديك
                throw new Exception("هذا الطلب لا يمكن تأكيده. حالته الحالية: " . $lockedOrder->status);
            }

            // 3. تحديث الحالة بشكل صريح
            $lockedOrder->update([
                'status' => 'processing', // أو استخدام Order::STATUS_PROCESSING
                'confirmed_by' => auth()->id(), // لتسجيل من قام بالتأكيد
                'confirmed_at' => now(),
            ]);

            // 4. إطلاق الـ Job بأمان تام بعد الـ Commit
            SplitOrderIntoVendorOrdersJob::dispatch($lockedOrder)->afterCommit();

            return $lockedOrder;
            
        }, 3); 
    }
}
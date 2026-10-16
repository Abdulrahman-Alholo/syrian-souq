<?php

namespace App\Observers;

use App\Modules\Order\Models\Order;
use App\Jobs\SplitOrderIntoVendorOrdersJob;

class OrderObserver
{
    /**
     * يتم استدعاء هذه الدالة عند تحديث أي حقل في الطلب
     */
    public function updated(Order $order): void
    {
        // 1. فحص إذا كان الحقل الذي تم تغييره هو "الحالة" 
        // وتأكد أن الحالة الجديدة هي "processing" (أي تم تأكيد الطلب من خدمة العملاء)
        if ($order->wasChanged('status') && $order->status === Order::STATUS_PROCESSING) {
            
            // 2. إطلاق الـ Job لتقسيم الطلب وإرساله للتجار
            SplitOrderIntoVendorOrdersJob::dispatch($order);
        }
    }
}
<?php

namespace App\Modules\Order\Observers;

use App\Modules\Order\Models\VendorOrder;
use App\Modules\Order\Services\OrderWorkflowService;
use Illuminate\Support\Facades\Log;

class VendorOrderObserver
{
    public function updated(VendorOrder $vendorOrder): void
    {
        // wasChanged('status') تكفي تماماً للتأكد أن الحالة تغيرت للتو في هذه الترانزاكشن
        if ($vendorOrder->wasChanged('status')) {
            
            $newStatus = $vendorOrder->status;
            $workflow = app(OrderWorkflowService::class);

            Log::info("✅ VendorOrderObserver: تغيرت حالة الطلب الفرعي #{$vendorOrder->id} إلى [{$newStatus}]");

            //  مسار التسليم الناجح (تحرير الأرباح وخصم المخزون)
            if ($newStatus === 'delivered') {
                $workflow->handleDelivered($vendorOrder);
            } 
            
            //  مسار الفشل / الإلغاء (تصفير الأرباح وإرجاع المخزون المحجوز)
            elseif (in_array($newStatus, ['returned', 'failed_delivery', 'cancelled'])) {
                $workflow->handleFailedDelivery($vendorOrder);
            }

            // دائماً زامن حالة الطلب الرئيسي (الأب) بناءً على حالات التجار
            $workflow->syncParentOrderStatus($vendorOrder->order_id);
        }
    }
}
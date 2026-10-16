<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\VendorOrder;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;
use Exception;

class VendorOrderService
{
    /**
     * تحديث حالة طلب التاجر من قبل التاجر نفسه
     */
    public function updateStatus(VendorOrder $vendorOrder, string $newStatus, int $currentVendorId): VendorOrder
    {
        // 1. حماية أمنية: التأكد أن التاجر يعدل طلبه الخاص فقط
        if ($vendorOrder->vendor_id !== $currentVendorId) {
            throw new Exception("غير مصرح لك بتعديل هذا الطلب.");
        }

        $validStatuses = [
            Order::STATUS_PROCESSING, 
            Order::STATUS_SHIPPED, 
            Order::STATUS_DELIVERED
        ];

        if (!in_array($newStatus, $validStatuses)) {
            throw new Exception("حالة الطلب غير صالحة للتاجر.");
        }

        return DB::transaction(function () use ($vendorOrder, $newStatus) {
            
            // 2. تحديث حالة طلب التاجر
            $vendorOrder->update(['status' => $newStatus]);

            // 3. 🔥 السحر هنا: مزامنة حالة الطلب الرئيسي للعميل
            $this->syncParentOrderStatus($vendorOrder->order_id);

            return $vendorOrder;
        });
    }

    /**
     * دالة ذكية لمزامنة حالة الطلب الرئيسي بناءً على طلبات التجار الفرعية
     */
    private function syncParentOrderStatus(int $parentOrderId): void
    {
        // نجلب الطلب الرئيسي مع كل طلباته الفرعية المقفلة (Pessimistic Read)
        $parentOrder = Order::with('vendorOrders')->sharedLock()->find($parentOrderId);
        
        if (!$parentOrder) return;

        // تجميع كل الحالات الحالية لطلبات التجار
        $vendorStatuses = $parentOrder->vendorOrders->pluck('status')->unique();

        $newParentStatus = $parentOrder->status;

        // اللوجيك الذكي:
        if ($vendorStatuses->count() === 1) {
            // إذا كل التجار عندهم نفس الحالة (مثلاً كلهم شحنوا)، الطلب الرئيسي بياخد نفس الحالة
            $newParentStatus = $vendorStatuses->first();
        } else {
            // إذا الحالات مختلفة (واحد شحن وواحد لسا قيد التجهيز)
            if ($vendorStatuses->contains(Order::STATUS_SHIPPED) || $vendorStatuses->contains(Order::STATUS_DELIVERED)) {
                $newParentStatus = 'partially_shipped'; // مشحون جزئياً
            } else {
                $newParentStatus = Order::STATUS_PROCESSING;
            }
        }

        // تحديث الطلب الرئيسي إذا تغيرت الحالة
        if ($parentOrder->status !== $newParentStatus) {
            $parentOrder->update(['status' => $newParentStatus]);
        }
    }
}
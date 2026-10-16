<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\VendorOrder;
use App\Modules\Order\Models\Order;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Finance\Services\LedgerService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderWorkflowService
{
    /**
     * معالجة الطلب عند تسليمه بنجاح
     */
    public function handleDelivered(VendorOrder $vendorOrder): void
    {
        try {
            // 1. خصم المخزون نهائياً
            $this->finalizeStockDeduction($vendorOrder);
            
            // 2. إيداع الأرباح مالياً
            app(LedgerService::class)->creditOrderEarnings($vendorOrder);
            
            // 3. إرسال إشعار للتاجر
            $this->sendNotificationToVendor(
                $vendorOrder, 
                '💰 أرباح جديدة!', 
                "تم إضافة " . number_format($vendorOrder->vendor_earnings, 0) . " SYP لمحفظتك من الطلب #{$vendorOrder->order->order_number}", 
                'success'
            );
            
        } catch (\Exception $e) {
            Log::error("❌ فشل معالجة التسليم للطلب {$vendorOrder->id}: " . $e->getMessage());
        }
    }

    /**
     * معالجة فشل التوصيل أو الإلغاء
     */
    public function handleFailedDelivery(VendorOrder $vendorOrder): void
    {
        DB::transaction(function () use ($vendorOrder) {
            // 1. تصفير الأرباح
            $vendorOrder->updateQuietly([
                'vendor_earnings'   => 0,
                'commission_amount' => 0,
            ]);

            // 2. فك حجز المخزون
            $this->releaseReservedStock($vendorOrder);
        }, 3);

        // 3. إرسال إشعار للتاجر
        $this->sendNotificationToVendor(
            $vendorOrder, 
            '🛑 فشل تسليم طلب', 
            "الطلب #{$vendorOrder->order->order_number} لم يتم تسليمه. تم فك حجز المخزون وإلغاء الأرباح.", 
            'danger'
        );
    }

    /**
     * مزامنة حالة الطلب الأب
     */
    public function syncParentOrderStatus(int $parentOrderId): void
    {
        $parentOrder = Order::with('vendorOrders')->find($parentOrderId);
        if (!$parentOrder) return;

        $vendorStatuses = $parentOrder->vendorOrders->pluck('status')->unique();
        $newParentStatus = $parentOrder->status;

        if ($vendorStatuses->count() === 1) {
            $newParentStatus = $vendorStatuses->first();
        } else {
            $shippedStatuses = ['shipped', 'delivered'];
            if ($vendorStatuses->intersect($shippedStatuses)->isNotEmpty()) {
                $newParentStatus = 'partially_shipped'; 
            } else {
                $newParentStatus = 'processing';
            }
        }

        if ($parentOrder->status !== $newParentStatus) {
            $parentOrder->updateQuietly(['status' => $newParentStatus]);
        }
    }

    // ==========================================
    // Internal Logic Helpers (يمكن نقلها لـ Services مستقلة لاحقاً)
    // ==========================================

    private function finalizeStockDeduction(VendorOrder $vendorOrder): void
    {
        DB::transaction(function () use ($vendorOrder) {
            $vendorOrder->loadMissing('items.productVariant');
            $variantIds = $vendorOrder->items->pluck('product_variant_id')->toArray();
            sort($variantIds);

            $lockedVariants = ProductVariant::whereIn('id', $variantIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($vendorOrder->items as $item) {
                if ($variant = $lockedVariants->get($item->product_variant_id)) {
                    $variant->update([
                        'stock'          => max(0, $variant->stock - $item->quantity),
                        'reserved_stock' => max(0, $variant->reserved_stock - $item->quantity)
                    ]);
                }
            }
        }, 3);
    }

    private function releaseReservedStock(VendorOrder $vendorOrder): void
    {
        $vendorOrder->loadMissing('items.productVariant');
        $variantIds = $vendorOrder->items->pluck('product_variant_id')->toArray();
        sort($variantIds);

        $lockedVariants = ProductVariant::whereIn('id', $variantIds)->lockForUpdate()->get()->keyBy('id');

        foreach ($vendorOrder->items as $item) {
            if ($variant = $lockedVariants->get($item->product_variant_id)) {
                $variant->update([
                    'reserved_stock' => max(0, $variant->reserved_stock - $item->quantity)
                ]);
            }
        }
    }

    private function sendNotificationToVendor(VendorOrder $vendorOrder, string $title, string $body, string $status): void
    {
        $vendorUser = $vendorOrder->vendor->user;
        if ($vendorUser) {
            Notification::make()
                ->title($title)
                ->body($body)
                ->$status()
                ->sendToDatabase($vendorUser);
        }
    }
}
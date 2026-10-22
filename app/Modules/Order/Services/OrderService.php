<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Catalog\Models\ProductVariant;
use App\Jobs\SplitOrderIntoVendorOrdersJob;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    /**
     * تغيير حالة الطلب العامة
     */
    public function updateOrderStatus(Order $order, string $newStatus): Order
    {
        $validStatuses = [
            Order::STATUS_PENDING, 
            Order::STATUS_PROCESSING, 
            Order::STATUS_SHIPPED, 
            Order::STATUS_DELIVERED, 
            Order::STATUS_CANCELLED
        ];

        if (!in_array($newStatus, $validStatuses)) {
            throw new Exception("حالة الطلب غير صالحة.");
        }

        if ($newStatus === Order::STATUS_CANCELLED) {
            return $this->cancelOrder($order);
        }

        $order->update(['status' => $newStatus]);

        if ($newStatus === Order::STATUS_DELIVERED && $order->payment_method === Order::PAYMENT_COD) {
            $order->update(['paid_at' => now()]);
        }

        return $order;
    }

    /**
     * تأكيد الدفع الإلكتروني (تُستدعى عبر Webhook أو الإدارة)
     */
    public function confirmPayment(Order $order, ?string $transactionReference = null): Order
    {
        return DB::transaction(function () use ($order, $transactionReference) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder || $lockedOrder->status !== Order::STATUS_PENDING) {
                throw new Exception("لا يمكن تأكيد الدفع. الطلب ليس قيد الانتظار أو تم التعامل معه مسبقاً.");
            }

            $lockedOrder->update([
                'status'            => Order::STATUS_PROCESSING,
                'payment_reference' => $transactionReference,
                'paid_at'           => now(),
            ]);

            // إطلاق التقسيم عبر المحرك الموحد بعد حفظ المعاملة
            SplitOrderIntoVendorOrdersJob::dispatch($lockedOrder)->afterCommit();

            return $lockedOrder;
        });
    }

    /**
     * إلغاء الطلب وفك حجز المخزون المحجوز (reserved_stock) بأمان
     */
    public function cancelOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder || in_array($lockedOrder->status, [Order::STATUS_CANCELLED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])) {
                throw new Exception("لا يمكن إلغاء هذا الطلب في حالته الحالية.");
            }

            $variantIds = $lockedOrder->items->pluck('product_variant_id')->toArray();
            sort($variantIds);

            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // ✅ تصحيح حاسم: إنقاص المخزون المحجوز (reserved_stock) وليس زيادة المخزون العام!
            foreach ($lockedOrder->items as $item) {
                if ($variant = $lockedVariants->get($item->product_variant_id)) {
                    $variant->update([
                        'reserved_stock' => max(0, $variant->reserved_stock - $item->quantity)
                    ]);
                }
            }

            // إلغاء الطلبات الفرعية للتجار إن وجدت وتصفير أرباحها
            foreach ($lockedOrder->vendorOrders as $vo) {
                $vo->updateQuietly([
                    'status'            => 'cancelled',
                    'vendor_earnings'   => 0,
                    'commission_amount' => 0,
                ]);
            }

            $lockedOrder->update([
                'status' => Order::STATUS_CANCELLED,
            ]);

            return $lockedOrder;
        }, 3);
    }
}
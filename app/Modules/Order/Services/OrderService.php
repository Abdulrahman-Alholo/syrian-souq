<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    /**
     * تغيير حالة الطلب العامة (تجهيز، شحن، توصيل)
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

        // إذا كانت الحالة المطلوبة هي "إلغاء"، نوجهها لدالة الإلغاء الآمنة لترجيع المخزون
        if ($newStatus === Order::STATUS_CANCELLED) {
            return $this->cancelOrder($order);
        }

        $order->update(['status' => $newStatus]);

        // لفتة ذكية: إذا كان الدفع عند الاستلام وتغيرت الحالة لـ "تم التوصيل"، نعتبره اندفع
        if ($newStatus === Order::STATUS_DELIVERED && $order->payment_method === Order::PAYMENT_COD) {
            $order->update(['paid_at' => now()]);
        }

        return $order;
    }

    /**
     * تأكيد الدفع (تُستدعى يدوياً من الإدارة أو أوتوماتيكياً عبر API Webhook)
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

            // 👇 هنا نضع تشغيل الـ Job بعد تأكيد استلام الحوالة من قبلك
            \App\Jobs\SplitOrderIntoVendorOrdersJob::dispatch($lockedOrder);

            return $lockedOrder;
        });
    }

    /**
     * إلغاء الطلب واسترجاع المخزون بطريقة آمنة (Pessimistic Locking)
     */
    public function cancelOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder || in_array($lockedOrder->status, [Order::STATUS_CANCELLED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])) {
                throw new Exception("لا يمكن إلغاء هذا الطلب في حالته الحالية.");
            }

            $variantIds = $lockedOrder->items->pluck('product_variant_id')->toArray();
            sort($variantIds); // ترتيب تصاعدي لمنع Deadlock

            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                                            ->orderBy('id')
                                            ->lockForUpdate()
                                            ->get()
                                            ->keyBy('id');

            // إرجاع المخزون
            foreach ($lockedOrder->items as $item) {
                $variant = $lockedVariants->get($item->product_variant_id);
                if ($variant) {
                    $variant->increment('stock', $item->quantity);
                }
            }

            $lockedOrder->update([
                'status' => Order::STATUS_CANCELLED,
            ]);

            return $lockedOrder;
        }, 3);
    }

    public function splitIntoVendorOrders(Order $order): void
    {
        // 1. Idempotency Check (Level 1): هل الطلب مقسوم مسبقاً؟
        if ($order->vendor_orders_created) {
            return;
        }

        // 2. جلب البيانات بـ Eager Loading لمنع N+1 Queries لاحقاً
        $order->loadMissing('items.productVariant.product');

        DB::transaction(function () use ($order) {
            
            // 3. Pessimistic Locking: نقفل الطلب الرئيسي لمنع أي عملية متزامنة من تقسيمه مرتين
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            // Idempotency Check (Level 2): تأكيد إضافي بعد الـ Lock
            if ($lockedOrder->vendor_orders_created) {
                return;
            }

            // 4. تجميع العناصر حسب التاجر
            $itemsByVendor = $lockedOrder->items->groupBy(function ($item) {
                return $item->productVariant->product->vendor_id;
            });

            foreach ($itemsByVendor as $vendorId => $items) {
                
                // 5. الحسابات المالية الدقيقة لكل تاجر
                $vendorSubtotal = $items->sum(function ($item) {
                    return $item->quantity * $item->unit_price;
                });

                // هنا يمكنك لاحقاً إضافة المنطق الخاص بتوزيع تكلفة الشحن والخصومات
                // $vendorShipping = ...
                // $vendorDiscount = ...
                $vendorTotal = $vendorSubtotal; // + $vendorShipping - $vendorDiscount;

                // 6. إنشاء الطلب الفرعي
                $vendorOrder = VendorOrder::create([
                    'order_id'      => $lockedOrder->id,
                    'vendor_id'     => $vendorId,
                    'status'        => Order::STATUS_PENDING,
                    'subtotal'      => $vendorSubtotal,
                    'total'         => $vendorTotal,
                    // إنشاء رقم تتبع يدمج رقم الطلب مع التاجر ليراه العميل (مثال: ORD-105-V1)
                    'order_number'  => $lockedOrder->id . '-' . $vendorId, 
                ]);

                // 7. 🔥 Bulk Update: ربط العناصر بالطلب الفرعي باستعلام واحد فقط (أداء عالي)
                $itemIds = $items->pluck('id')->toArray();
                OrderItem::whereIn('id', $itemIds)->update([
                    'vendor_order_id' => $vendorOrder->id
                ]);
            }

            // 8. تحديث حالة الطلب الرئيسي لضمان عدم تقسيمه مرة أخرى أبداً
            $lockedOrder->update(['vendor_orders_created' => true]);
        });
    }
}
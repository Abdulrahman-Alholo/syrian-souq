<?php

namespace App\Modules\Cart\Services;

use App\Modules\Cart\Models\Cart;
use App\Modules\Order\Models\Order;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use DomainException;
use Exception;

class CheckoutService
{
    /**
     * إتمام الطلب بمستوى عالي من الحماية والتوازي
     * (Idempotent, Bulk Locking, Deadlock Prevention)
     *
     * @param Cart $cart
     * @param array $shippingData
     * @param string $paymentMethod
     * @param string|null $idempotencyKey
     * @return Order
     * @throws DomainException|Exception
     */
    public function processCheckout(
        Cart $cart, 
        array $shippingData, 
        string $paymentMethod = Order::PAYMENT_COD, 
        ?string $idempotencyKey = null
    ): Order {
        
        // 1. حماية Application-level للـ Idempotency لمنع تكرار الطلب
        if ($idempotencyKey && Order::where('idempotency_key', $idempotencyKey)->exists()) {
            return Order::where('idempotency_key', $idempotencyKey)->first();
        }

        // 🔥 معاملة قاعدة البيانات مع إعادة المحاولة 3 مرات في حال حدوث Deadlock بسبب التزامن العالي
        return DB::transaction(function () use ($cart, $shippingData, $paymentMethod, $idempotencyKey) {
            
            // 2. قفل السلة الحالية لمنع التعديل عليها أثناء المعالجة
            $lockedCart = Cart::where('id', $cart->id)->lockForUpdate()->first();

            if (!$lockedCart || !in_array($lockedCart->status, ['active', 'reserved']) || $lockedCart->items->isEmpty()) {
                throw new DomainException("السلة فارغة أو لم تعد صالحة لإتمام الطلب.");
            }

            // 3. ترتيب العناصر تصاعدياً حسب الـ ID (Deadlock Prevention Strategy)
            $sortedItems = $lockedCart->items->sortBy('product_variant_id');
            $variantIds = $sortedItems->pluck('product_variant_id')->toArray();

            // 4. 🔥 الـ Bulk Locking: قفل جميع النسخ بضربة واحدة داتا بيز مرتبة
            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $orderItemsData = [];

            // 5. الدوران على عناصر السلة وحساب القيم وفحص المخزون
            foreach ($sortedItems as $item) {
                $variant = $lockedVariants->get($item->product_variant_id);

                // فحص المخزون المتاح الفعلي (الأساسي مطروحاً منه المحجوز)
                if (!$variant || $variant->available_stock < $item->quantity) {
                    $productName = $variant?->product?->name_ar ?? 'منتج غير محدد'; 
                    throw new DomainException("المنتج ({$productName}) لم يعد متوفراً بالكمية المطلوبة. المتاح الفعلي: " . ($variant->available_stock ?? 0));
                }

                // حساب الإجمالي بالاعتماد على السعر النهائي (الذي يشمل الخصومات الفعالة)
                $itemTotal = $item->quantity * $variant->final_price;
                $subtotal += $itemTotal;

                $orderItemsData[] = [
                    'product_variant_id'  => $variant->id,
                    'product_name'        => $variant->product->name_ar ?? 'منتج غير محدد', 
                    'variant_description' => $this->generateVariantDescription($variant),
                    'quantity'            => $item->quantity,
                    'unit_price'          => $variant->final_price,
                ];

                // نظام الحجز: إذا لم تمر السلة بخطوة التثبيت المسبق، نحجز الكمية الآن بالـ DB
                if ($lockedCart->status === 'active') {
                    $variant->increment('reserved_stock', $item->quantity);
                }
            } // 👈 تم إصلاح القوس المفقود هنا بنجاح

            // 6. الحسابات المالية الإجمالية للطلب
            $shippingPrice = 0; 
            $discount = 0;      
            $totalPrice = $subtotal + $shippingPrice - $discount;

            // 7. إنشاء الطلب الرئيسي
            $order = Order::create([
                'user_id'               => $lockedCart->user_id,
                'cart_id'               => $lockedCart->id,
                'idempotency_key'       => $idempotencyKey,
                'status'                => 'pending',
                'subtotal'              => $subtotal,
                'shipping_price'        => $shippingPrice,
                'discount'              => $discount,
                'total_price'           => $totalPrice,
                'payment_method'        => $paymentMethod,
                'currency'              => 'SYP', // متوافق مع عملة المتجر الافتراضية
                'shipping_name'         => $shippingData['shipping_name'],
                'shipping_phone'        => $shippingData['shipping_phone'],
                'shipping_city'         => $shippingData['shipping_city'],
                'shipping_address_line' => $shippingData['shipping_address_line'],
                'notes'                 => $shippingData['notes'] ?? null,
            ]);

            // 8. حفظ عناصر الطلب دفعة واحدة لتخفيف استعلامات الـ SQL
            $order->items()->createMany($orderItemsData);

            // 9. تحويل حالة السلة إلى مستهلكة بنجاح
            $lockedCart->update(['status' => 'converted']);

            // 🚀 هنا سيتم إلغاء تعليق الـ Job بمجرد إعداد الـ Queue لتقسيم الطلب على التجار
            // if ($paymentMethod === Order::PAYMENT_COD) {
            //     \App\Jobs\SplitOrderIntoVendorOrdersJob::dispatch($order);
            // }

            \App\Jobs\SplitOrderIntoVendorOrdersJob::dispatchSync($order);

            return $order;

        }, 3); 
    }

    /**
     * إلغاء آمن للطلب وفك حجز المنتجات وإعادتها للمستودع
     *
     * @param Order $order
     * @return Order
     * @throws Exception
     */
    public function cancelOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            
            // قفل الطلب لمنع الـ Race Condition في حال محاولة إلغاء متزامنة
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder || in_array($lockedOrder->status, ['cancelled', 'shipped', 'delivered'])) {
                throw new Exception("لا يمكن إلغاء هذا الطلب في حالته الحالية.");
            }

            // جلب وترتيب الـ IDs لمنع الـ Deadlock أثناء فك الحجز
            $variantIds = $lockedOrder->items->pluck('product_variant_id')->toArray();
            sort($variantIds);

            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // فك الحجز عن المستودع وإعادة الكميات المتاحة للبيع طبيعياً
            foreach ($lockedOrder->items as $item) {
                $variant = $lockedVariants->get($item->product_variant_id);

                if ($variant) {
                    $variant->decrement('reserved_stock', $item->quantity);
                }
            }

            // تحديث حالة الطلب النهائي
            $lockedOrder->update([
                'status' => 'cancelled',
            ]);

            return $lockedOrder;
        }, 3);
    }

    /**
     * حجز عناصر السلة مؤقتاً (مثلاً أثناء التواجد في صفحة الدفع لفترة محدودة)
     *
     * @param Cart $cart
     * @return bool
     * @throws Exception
     */
    public function reserveCartItems(Cart $cart): bool
    {
        return DB::transaction(function () use ($cart) {
            
            $lockedCart = Cart::where('id', $cart->id)->lockForUpdate()->first();

            if (!$lockedCart || $lockedCart->status !== 'active') {
                throw new Exception("السلة غير صالحة للحجز.");
            }

            $sortedItems = $lockedCart->items->sortBy('product_variant_id');
            $variantIds = $sortedItems->pluck('product_variant_id')->toArray();

            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($sortedItems as $item) {
                $variant = $lockedVariants->get($item->product_variant_id);

                if (!$variant || $variant->available_stock < $item->quantity) {
                    throw new Exception("المنتج غير متوفر بالكمية المطلوبة. يرجى تحديث السلة.");
                }

                // رفع قيمة المحجوز بالـ DB وحفظ القيمة بداخل عنصر السلة للرجوع إليها
                $variant->increment('reserved_stock', $item->quantity);
                $item->update([
                    'reserved_quantity' => $item->quantity
                ]);
            }

            // تحديث حالة السلة وتسجيل وقت الانتهاء للـ Cron Job المسؤول عن فك الحجز تلقائياً
            $lockedCart->update([
                'status'      => 'reserved',
                'reserved_at' => now(), 
            ]);

            return true;
        }, 3);
    }

    /**
     * توليد وصف نصي منسق لخصائص المنتج المتغيرة
     *
     * @param ProductVariant $variant
     * @return string
     */
    private function generateVariantDescription(ProductVariant $variant): string
    {
        $descriptionParts = [];
        $variant->loadMissing('attributeValues.attribute'); 

        foreach ($variant->attributeValues as $value) {
            $attributeName = $value->attribute->name_ar ?? '';
            $valueName = $value->value_ar ?? '';
            if ($attributeName && $valueName) {
                $descriptionParts[] = "{$attributeName}: {$valueName}";
            }
        }

        return implode(' - ', $descriptionParts) ?: ($variant->sku ?? 'نسخة افتراضية');
    }
}
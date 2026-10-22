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

        return DB::transaction(function () use ($cart, $shippingData, $paymentMethod, $idempotencyKey) {
            
            // 2. قفل السلة الحالية لمنع التعديل عليها أثناء المعالجة
            $lockedCart = Cart::where('id', $cart->id)->lockForUpdate()->first();

            if (!$lockedCart || !in_array($lockedCart->status, ['active', 'reserved']) || $lockedCart->items->isEmpty()) {
                throw new DomainException("السلة فارغة أو لم تعد صالحة لإتمام الطلب.");
            }

            // 3. ترتيب العناصر تصاعدياً حسب الـ ID (Deadlock Prevention Strategy)
            $sortedItems = $lockedCart->items->sortBy('product_variant_id');
            $variantIds = $sortedItems->pluck('product_variant_id')->toArray();

            // 4. قفل جميع النسخ بضربة واحدة
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

                if (!$variant || $variant->available_stock < $item->quantity) {
                    $productName = $variant?->product?->name_ar ?? 'منتج غير محدد'; 
                    throw new DomainException("المنتج ({$productName}) لم يعد متوفراً بالكمية المطلوبة. المتاح الفعلي: " . ($variant->available_stock ?? 0));
                }

                $itemTotal = $item->quantity * $variant->final_price;
                $subtotal += $itemTotal;

                $orderItemsData[] = [
                    'product_variant_id'  => $variant->id,
                    'product_name'        => $variant->product->name_ar ?? 'منتج غير محدد', 
                    'variant_description' => $this->generateVariantDescription($variant),
                    'quantity'            => $item->quantity,
                    'unit_price'          => $variant->final_price,
                ];

                if ($lockedCart->status === 'active') {
                    $variant->increment('reserved_stock', $item->quantity);
                }
            }

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
                'currency'              => 'SYP',
                'shipping_name'         => $shippingData['shipping_name'],
                'shipping_phone'        => $shippingData['shipping_phone'],
                'shipping_city'         => $shippingData['shipping_city'],
                'shipping_address_line' => $shippingData['shipping_address_line'],
                'notes'                 => $shippingData['notes'] ?? null,
            ]);

            // 8. حفظ عناصر الطلب دفعة واحدة
            $order->items()->createMany($orderItemsData);

            // 9. تحويل حالة السلة إلى مستهلكة بنجاح
            $lockedCart->update(['status' => 'converted']);

            // 🚀 إذا كان الدفع عند الاستلام (COD)، نقسم الطلب للتجار فور انتهاء الترانزاكشن
            if ($paymentMethod === Order::PAYMENT_COD) {
                \App\Jobs\SplitOrderIntoVendorOrdersJob::dispatch($order)->afterCommit();
            }

            return $order;

        }, 3); 
    }

    /**
     * إلغاء آمن للطلب وفك حجز المنتجات (تفويض لـ OrderService لتوحيد المنطق)
     */
    public function cancelOrder(Order $order): Order
    {
        return app(\App\Modules\Order\Services\OrderService::class)->cancelOrder($order);
    }

    /**
     * حجز عناصر السلة مؤقتاً
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

                $variant->increment('reserved_stock', $item->quantity);
                $item->update([
                    'reserved_quantity' => $item->quantity
                ]);
            }

            $lockedCart->update([
                'status'      => 'reserved',
                'reserved_at' => now(), 
            ]);

            return true;
        }, 3);
    }

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
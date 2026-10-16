<?php

namespace App\Modules\Cart\Services;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use DomainException;

class CartService
{
    protected const COOKIE_NAME = 'guest_cart_token';
    protected const COOKIE_EXPIRATION = 60 * 24 * 30;

    // 1. تحديث دالة getCart لتجلب العلاقات دفعة واحدة (Eager Loading) منعاً للضغط

    public function getCart(): Cart
    {
        // 1. إذا كان المستخدم مسجل دخول، نجلب سلته المرتبطة بحسابه
        if (auth()->check()) {
            return Cart::firstOrCreate([
                'user_id' => auth()->id(),
                'status' => 'active'
            ]);
        }

        // 2. إذا كان زائر، نبحث عن التوكن في الكوكيز
        $token = Cookie::get(self::COOKIE_NAME);

        // إذا ما عنده توكن (أول مرة يزور الموقع)، ننشئ له توكن جديد ونحفظه كـ Cookie
        if (!$token) {
            $token = Str::uuid()->toString();
            Cookie::queue(self::COOKIE_NAME, $token, self::COOKIE_EXPIRATION);
        }

        // نجلب سلة الزائر بناءً على التوكن
        return Cart::firstOrCreate([
            'session_id' => $token,
            'status' => 'active'
        ]);
    }
    public function getCartWithItems(): Cart
    {
        $cart = $this->getCart();
        
        // جلب العناصر مع الصور والخصائص بضربة واحدة للداتا بيز
        $cart->loadMissing([
            'items.variant.product.media', // لجلب الصورة
            'items.variant.attributeValues.attribute'
        ]);

        return $cart;
    }

    // 2. تحديث دالة التحديث لتمنع الصفر والكميات السالبة
    public function updateItemQuantity(int $itemId, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw new DomainException("لا يمكن أن تكون الكمية أقل من 1. للحذف استخدم زر الإزالة.");
        }

        $cart = $this->getCart();
        
        // 🔒 حماية أمنية: التأكد أن العنصر يتبع لسلة المستخدم الحالي
        $item = $cart->items()->where('id', $itemId)->first();

        if (!$item) {
            throw new DomainException("هذا المنتج غير موجود في سلتك أو لا تملك صلاحية تعديله.");
        }

        $variant = ProductVariant::findOrFail($item->product_variant_id);

        if ($quantity > $variant->available_stock) {
            throw new DomainException("عذراً، الكمية المطلوبة تتجاوز المخزون. المتوفر: {$variant->available_stock} قطع.");
        }

        $item->update(['quantity' => $quantity]);
        return $item;
    }

    // 3. دالة الحذف الآمنة (تلبية لملاحظة الـ GPT الأمنية)
    public function removeItem(int $itemId): void
    {
        $cart = $this->getCart();
        
        // 🔒 حماية: لا يمكن حذف إلا العناصر التي تنتمي للسلة الحالية
        $cartItem = $cart->items()->where('id', $itemId)->first();
        
        if ($cartItem) {
            $cartItem->delete();
        } else {
            throw new DomainException("العنصر غير موجود أو تم حذفه مسبقاً.");
        }
    }

    public function addItem(int $variantId, int $quantity = 1): CartItem
    {
        if ($quantity <= 0) throw new DomainException("الكمية يجب أن تكون أكبر من صفر.");

        $cart = $this->getCart();
        $variant = ProductVariant::findOrFail($variantId);
        $item = $cart->items()->where('product_variant_id', $variantId)->first();

        $newQuantity = $item ? $item->quantity + $quantity : $quantity;

        // 🟢 التعديل 1: استخدام available_stock (المتاح فعلياً بعد خصم المحجوز) بدلاً من stock العام
        if ($newQuantity > $variant->available_stock) {
            throw new DomainException("عذراً، الكمية المطلوبة تتجاوز المخزون. المتوفر: {$variant->available_stock} قطع.");
        }

        if ($item) {
            $item->update(['quantity' => $newQuantity]);
            return $item;
        }

        return $cart->items()->create(['product_variant_id' => $variantId, 'quantity' => $quantity]);
    }
    
    public function calculateTotals(Cart $cart): array
    {
        $cart->loadMissing('items.variant.attributeValues');
        
        $subtotal = $cart->items->sum(function ($item) {
            // 🟢 التعديل 3: استخدام final_price لضمان احتساب الخصومات إذا وجدت
            return $item->quantity * ($item->variant->final_price ?? 0);
        });

        return [
            'items_count' => $cart->items->sum('quantity'),
            'subtotal'    => (float) $subtotal,
        ];
    }
}
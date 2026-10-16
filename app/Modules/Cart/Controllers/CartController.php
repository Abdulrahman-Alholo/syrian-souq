<?php

namespace App\Modules\Cart\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\Cart\Services\CheckoutService;
use App\Modules\Payment\Services\PaymentService;
use DomainException; // 🟢 فقط الأخطاء المنطقية
use Exception;

class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function index()
    {
        $cart = $this->cartService->getCart();
        $totals = $this->cartService->calculateTotals($cart); // 🟢 استدعاء المجاميع من الـ Service

        // 🟢 تحويل البيانات لشكل يشبه الـ Resource لمنع تسريب الداتا
        $items = $cart->items->map(function ($item) {
            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'price' => $item->variant->price,
                'total' => $item->quantity * $item->variant->price,
                'variant_id' => $item->variant->id,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'cart_id' => $cart->id,
                'items_count' => $totals['items_count'],
                'subtotal' => $totals['subtotal'],
                'items' => $items,
            ]
        ]);
    }

    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        try {
            $this->cartService->addItem($request->product_variant_id, $request->quantity);
            return response()->json(['success' => true, 'message' => 'تمت الإضافة بنجاح! 🛒']);
        } catch (DomainException $e) { // 🟢 صيد أخطاء البزنس فقط
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:cart_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        try {
            // 🟢 استخدام الدالة الصريحة للتحديث
            $this->cartService->updateItemQuantity($request->item_id, $request->quantity);
            return response()->json(['success' => true, 'message' => 'تم تحديث الكمية بنجاح']);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function remove($itemId)
    {
        $cart = $this->cartService->getCart();
        $item = $cart->items()->where('id', $itemId)->first();

        if ($item) {
            $item->delete();
            return response()->json(['success' => true, 'message' => 'تم حذف المنتج']);
        }

        return response()->json(['success' => false, 'message' => 'المنتج غير موجود'], 404);
    }

    public function pay(CheckoutService $checkoutService, PaymentService $paymentService)
    {
        try {
            // 1. 💡 تجاوز فقدان الذاكرة في الـ API: جلب آخر سلة نشطة "مفخخة" بالمنتجات
            $cart = \App\Modules\Cart\Models\Cart::where('status', 'active')
                                                 ->whereHas('items')
                                                 ->latest()
                                                 ->first();

            if (!$cart) {
                return response()->json(['error' => 'لا توجد سلة معبأة! تأكد من فتح رابط /force-fill-cart أولاً.'], 400);
            }

            // 2. بيانات شحن وهمية
            $shippingData = [
                'shipping_name'         => 'عبدالرحمن الحلو', 
                'shipping_phone'        => '07999999999',
                'shipping_city'         => 'بغداد', 
                'shipping_address_line' => 'شارع 14 رمضان',
                'notes'                 => 'طلب تجريبي لاختبار Stripe'
            ];

            // 3. توليد مفتاح حماية التكرار 
            $idempotencyKey = (string) \Illuminate\Support\Str::uuid();

            // 4. إنشاء الطلب (Order) من السلة المفخخة
            $order = $checkoutService->processCheckout($cart, $shippingData, 'stripe', $idempotencyKey);

            // 5. الاتصال بـ Stripe لإنشاء نية الدفع
            $paymentData = $paymentService->initiate($order, 'stripe', [], $idempotencyKey);

            // 6. استخراج الـ Client Secret بأمان
            $clientSecret = data_get($paymentData, 'payment.meta.client_secret') 
                         ?? data_get($paymentData, 'meta.client_secret');

            if (!$clientSecret) {
                throw new \Exception("لم يتم إرجاع Client Secret من بوابات Stripe.");
            }

            return response()->json([
                'success' => true,
                'client_secret' => $clientSecret
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
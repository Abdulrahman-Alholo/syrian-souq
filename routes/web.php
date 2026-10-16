<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Payment\Models\Payment;
use Livewire\Volt\Volt;

Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

Route::get('/', function () {
    return view('welcome');
});


// مسار لفتح صفحة الدفع التجريبية
Route::get('/checkout/test', function () {
    return view('checkout'); // هذا سيبحث عن ملف resources/views/checkout.blade.php
});


Route::get('/checkout/success', function (\Illuminate\Http\Request $request) {
    $intentId = $request->query('payment_intent');

    if (!$intentId) {
        return redirect('/')->with('error', 'طلب غير صالح.');
    }

    // نبحث عن الدفعة ونجلب الطلب المرتبط بها
    $payment = Payment::with('order')->where('transaction_id', $intentId)->first();

    // 🛡️ حماية: التأكد أن الدفعة موجودة وحالتها ناجحة والطلب مدفوع
    if (!$payment || $payment->status !== 'succeeded' || $payment->order->status !== 'paid') {
        // إذا لم يصل الـ Webhook بعد، يمكننا عرض صفحة "جاري معالجة طلبك"
        return "جاري تأكيد الدفع مع البنك... يرجى تحديث الصفحة بعد ثوانٍ."; 
    }

    // هنا تضع الـ View الجميل الخاص بك
    return view('checkout-success', ['order' => $payment->order]);
});

Route::get('/force-fill-cart', function (CartService $cartService) {
    // جلب أول نسخة منتج من الداتا بيز
    $variant = ProductVariant::first(); 
    
    if (!$variant) {
        return "الكارثة: ليس لديك أي منتجات (Variants) في قاعدة البيانات! قم بإنشاء منتج أولاً.";
    }

    // إضافة المنتج للسلة
    $cartService->addItem($variant->id, 1);
    
    return "تم تفخيخ السلة بمنتج بنجاح! 🛒 اذهب الآن إلى صفحة /checkout/test";
});

Volt::route('/cart', 'storefront.shopping-cart')->name('cart');
Volt::route('/checkout', 'storefront.checkout-page')->name('checkout');
Volt::route('/checkout/success', 'storefront.checkout-success')->name('checkout.success');
Volt::route('/', 'storefront.home-page')->name('home');
// أضف هذا السطر مع مسارات Volt في الأسفل
Volt::route('/my-orders', 'storefront.my-orders')->name('my-orders')->middleware('auth');
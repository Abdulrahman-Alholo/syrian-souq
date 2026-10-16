<?php
use App\Modules\Cart\Controllers\CartController;
use Illuminate\Support\Facades\Route;
use App\Modules\Payment\Contracts\WebhookController;
use App\Modules\Cart\Controllers\CheckoutController;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Vendor\Controllers\Api\OrderController as VendorOrderController;

// 1. مسار إرسال الـ Webhook من Stripe
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe']);

// 2. مسار تجريبي لإنشاء نية دفع (Payment Intent) وإرجاع الـ Client Secret للواجهة
Route::post('/checkout/pay', [CartController::class, 'pay']);

Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']); // عرض السلة
    Route::post('/add', [CartController::class, 'add']); // إضافة منتج
    Route::put('/update', [CartController::class, 'update']); // تحديث الكمية
    Route::delete('/remove/{itemId}', [CartController::class, 'remove']); // حذف منتج
});



Route::post('/test-refund', function (PaymentService $paymentService) {
    // نجلب آخر دفعة ناجحة عملتها أنت من قليل
    $payment = Payment::where('status', 'succeeded')->latest()->first();

    if (!$payment) return "لا توجد دفعات ناجحة لاسترجاعها!";

    try {
        $result = $paymentService->processRefund($payment);
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
});


// Route::middleware('auth:sanctum')->group(function () {
    // مسار إتمام الطلب (Checkout)
    Route::post('/checkout', [CheckoutController::class, 'submitCheckout']);
// });

// Route::middleware(['auth:sanctum', 'is_vendor'])->prefix('vendor/orders')->group(function () {
    
    Route::get('vendor/orders', [VendorOrderController::class, 'index']);
    Route::patch('/{id}/status', [VendorOrderController::class, 'updateStatus']);

// });
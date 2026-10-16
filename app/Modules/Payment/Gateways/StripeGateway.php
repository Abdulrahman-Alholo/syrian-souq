<?php

namespace App\Modules\Payment\Gateways;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\DTOs\PaymentResult; // 👈 استدعاء الـ DTO
use Stripe\Stripe;
use Stripe\PaymentIntent;

class StripeGateway implements PaymentGateway
{
    // 👈 تغيير نوع الإرجاع إلى PaymentResult
    public function initiatePayment(Order $order, array $data = []): PaymentResult
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $intent = PaymentIntent::create([
            'amount' => (int) ($order->total_price * 100), // Stripe تتعامل بأجزاء العملة (سنت/قرش)
            'currency' => strtolower($order->currency),
            'metadata' => [
                'order_id' => $order->id,
            ],
        ]);

        // 👈 إرجاع الكائن (DTO) بدلاً من مصفوفة
        return new PaymentResult(
            status: 'requires_action',
            redirectUrl: null,
            transactionId: $intent->id,
            meta: [
                'client_secret' => $intent->client_secret
            ]
        );
    }

    public function handleWebhook(array $payload): void
    {
        $eventType = $payload['type'] ?? null;
        $data = $payload['data']['object'] ?? [];

        $transactionId = $data['id'] ?? null;

        if (!$transactionId) return;

        $payment = Payment::where('transaction_id', $transactionId)->first();
        if (!$payment) return;

        match ($eventType) {
            'payment_intent.succeeded' => $this->markAsSucceeded($payment),
            'payment_intent.payment_failed' => $this->markAsFailed($payment, $data), // 👈 مررنا $data لنعرف السبب
            default => null,
        };
    }

    protected function markAsSucceeded(Payment $payment): void
    {
        if ($payment->status === 'succeeded') return; 

        $payment->update(['status' => 'succeeded']);

        $order = $payment->order;
        $order->update(['status' => 'paid']);

        // 🟢 الجديد: تفريغ السلة (أو تحويل حالتها) من الـ Backend بثقة 100%
        if ($order->cart_id) {
            // نغير حالة السلة إلى 'completed' بدلاً من مسحها، لتبقى في السجلات كأرشيف
            \App\Modules\Cart\Models\Cart::where('id', $order->cart_id)->update(['status' => 'completed']);
            }
        }

    protected function markAsFailed(Payment $payment, array $data = []): void
    {
        if ($payment->status === 'failed') return;

        // 1. استخراج سبب الفشل الدقيق من رد Stripe (مثلاً: رصيد غير كافٍ، بطاقة مسروقة...)
        $failureMessage = $data['last_payment_error']['message'] ?? 'سبب غير معروف (Unknown Error)';
        $failureCode = $data['last_payment_error']['code'] ?? 'N/A';

        // 2. تحديث الدفعة وحفظ السبب في الـ Meta لكي يراه التاجر أو الدعم الفني
        $payment->update([
            'status' => 'failed',
            'meta' => array_merge($payment->meta ?? [], [
                'failure_reason' => $failureMessage,
                'failure_code' => $failureCode
            ])
        ]);

        // 3. تحديث حالة الطلب لـ 'payment_failed' (وليس failed فقط، لنميزه عن الإلغاء العادي)
        $payment->order->update(['status' => 'payment_failed']);

        // 4. 🟢 ضربة الـ Senior: الـ Logging & Monitoring! 
        // سيتم تسجيل هذا الخطأ في ملف storage/logs/laravel.log لكي نراقبه
        \Illuminate\Support\Facades\Log::warning("🚨 Payment Failed! Order ID: {$payment->order->id} | Transaction: {$payment->transaction_id} | Reason: {$failureMessage} | Code: {$failureCode}");
    }

    /**
     * إرجاع الأموال للزبون (كلي أو جزئي)
     */
    public function refundPayment(string $transactionId, ?float $amount = null, ?string $reason = 'requested_by_customer'): array
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        // 🛡️ حماية التكرار: ننشئ مفتاح Idempotency خاص بعملية الاسترجاع هذه
        // ندمج الـ transaction_id مع الوقت لضمان فرادته، وفي حال تكرر نفس الطلب بالملي ثانية Stripe سيرفضه
        $idempotencyKey = 'refund_' . $transactionId . '_' . time();

        $refundParams = [
            'payment_intent' => $transactionId,
            'reason' => $reason,
        ];

        // إذا تم تمرير مبلغ، فهذا استرجاع جزئي، وإلا فـ Stripe سيرجع المبلغ كاملاً تلقائياً
        if ($amount) {
            $refundParams['amount'] = (int) ($amount * 100); // تحويل للسنت/قرش
        }

        try {
            $refund = \Stripe\Refund::create(
                $refundParams,
                ['idempotency_key' => $idempotencyKey] // 🛡️ ضربة الحماية
            );

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'status' => $refund->status, // عادة تكون 'succeeded' أو 'pending'
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            // تسجيل الخطأ لو أردت لاحقاً (Log::error)
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
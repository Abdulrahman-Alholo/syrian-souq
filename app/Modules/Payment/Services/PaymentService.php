<?php

namespace App\Modules\Payment\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Factories\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function initiate(Order $order, string $gateway, array $data = [], ?string $idempotencyKey = null): array
    {
        // 1. 🟢 Transaction سريعة جداً لإنشاء سجل الدفع فقط (تأخذ أجزاء من الثانية)
        $payment = DB::transaction(function () use ($order, $gateway, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) return $existing;
            }

            return Payment::create([
                'order_id' => $order->id,
                'gateway' => $gateway,
                'amount' => $order->total_price,
                'currency' => $order->currency,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
            ]);
        });

        // إذا كان الدفع موجوداً مسبقاً (Idempotency Hit)، نرجعه فوراً
        if ($payment->wasRecentlyCreated === false && $idempotencyKey) {
            return ['payment' => $payment, 'redirect_url' => $payment->meta['redirect_url'] ?? null];
        }

        // 2. 🟢 استدعاء الـ API الخارجي بحرية تامة وبدون خنق قاعدة البيانات!
        $gatewayInstance = PaymentGatewayFactory::make($gateway);
        $resultDTO = $gatewayInstance->initiatePayment($order, $data); // يرجع PaymentResult

        // 3. تحديث السجل بناءً على الرد
        $payment->update([
            'status' => $resultDTO->status,
            'transaction_id' => $resultDTO->transactionId,
            'meta' => $resultDTO->meta,
        ]);

        return [
            'payment' => $payment,
            'redirect_url' => $resultDTO->redirectUrl
        ];
    }

    /**
     * 🔥 معالجة الـ Webhook
     */
    public function handleWebhook(string $gateway, array $payload): void
    {
        $gatewayInstance = PaymentGatewayFactory::make($gateway);
        $gatewayInstance->handleWebhook($payload);
    }

    public function processRefund(\App\Modules\Payment\Models\Payment $payment, ?float $amount = null, string $reason = 'requested_by_customer'): array
    {
        // 1. 🛡️ State Validation: هل الدفعة قابلة للاسترجاع أصلاً؟
        if ($payment->status !== 'succeeded') {
            throw new \Exception("لا يمكن استرجاع هذه الفاتورة. حالتها الحالية: {$payment->status}");
        }

        // 2. استدعاء البوابة (Stripe)
        // تحديد البوابة ديناميكياً بناءً على اسم البوابة المحفوظة في الدفعة
        $gateway = match($payment->gateway) {
            'stripe' => app(\App\Modules\Payment\Gateways\StripeGateway::class),
            default => throw new \Exception("بوابة الدفع {$payment->gateway} غير مدعومة للاسترجاع حالياً."),
        };
        $result = $gateway->refundPayment($payment->transaction_id, $amount, $reason);

        if (!$result['success']) {
            throw new \Exception("فشل الاسترجاع من البوابة: " . $result['error']);
        }

        // 3. 🛡️ Database Consistency: تحديث الجداول
        // نستخدم DB Transaction لضمان تحديث الـ Payment والـ Order معاً أو التراجع عنهما
        \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $result) {
            $payment->update([
                'status' => 'refunded',
                // نحتفظ برقم الاسترجاع في الـ meta للتدقيق المالي لاحقاً
                'meta' => array_merge($payment->meta ?? [], [
                    'refund_id' => $result['refund_id'],
                    'refunded_at' => now()->toDateTimeString(),
                ])
            ]);

            // تحديث حالة الطلب لكي يعرف نظام الشحن أن الطلب أُلغي مالياً
            $payment->order->update(['status' => 'refunded']);
        });

        return [
            'success' => true,
            'message' => 'تم استرجاع الأموال بنجاح',
            'refund_id' => $result['refund_id']
        ];
    }

    public function retryFailedPayment(\App\Modules\Order\Models\Order $order): array
    {
        // 1. حماية: التأكد أن الطلب فعلاً فاشل مالياً
        if ($order->status !== 'payment_failed') {
            throw new \Exception("هذا الطلب ليس في حالة فشل مالي لإعادة المحاولة. حالته: {$order->status}");
        }

        // 2. توليد مفتاح Idempotency جديد كلياً لهذه المحاولة
        $newIdempotencyKey = (string) \Illuminate\Support\Str::uuid();

        // 3. الاتصال بـ Stripe لإنشاء نية دفع (Payment Intent) جديدة لهذا الطلب
        $paymentData = $this->initiate($order, 'stripe', [], $newIdempotencyKey);

        $clientSecret = data_get($paymentData, 'payment.meta.client_secret') 
                     ?? data_get($paymentData, 'meta.client_secret');

        if (!$clientSecret) {
            throw new \Exception("لم نتمكن من تهيئة جلسة دفع جديدة.");
        }

        return [
            'success' => true,
            'client_secret' => $clientSecret,
            'message' => 'تم تهيئة جلسة الدفع، يرجى إدخال بطاقة جديدة.'
        ];
    }
}
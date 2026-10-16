<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Modules\Payment\Models\WebhookEvent;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Factories\PaymentGatewayFactory;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5; 
    public int $timeout = 30; 
    public int $backoff = 10; 

    public function __construct(
        public string $gateway,
        public int $webhookEventId
    ) {}

    public function handle(): void
    {
        // 🔒 التعديل الجوهري: نفتح الـ Transaction أولاً لكي يعمل الـ Lock بشكل حقيقي!
        DB::transaction(function () {
            
            // الآن الـ Lock محمي داخل الـ Transaction
            $event = WebhookEvent::where('id', $this->webhookEventId)
                ->lockForUpdate()
                ->first();

            // إذا لم نجد الحدث، أو تمت معالجته مسبقاً (Idempotent)، نخرج بهدوء
            if (!$event || $event->status === 'processed') {
                return;
            }

            try {
                // 🎯 استدعاء الـ Gateway
                $gatewayInstance = PaymentGatewayFactory::make($this->gateway);
                $gatewayInstance->handleWebhook($event->payload);

                // 💡 التحديث الإضافي الذي اقترحه: ربط الـ Webhook بالـ Payment للـ Debugging
                $transactionId = $event->payload['data']['object']['id'] ?? null;
                $paymentId = null;
                if ($transactionId) {
                    $paymentId = Payment::where('transaction_id', $transactionId)->value('id');
                }

                // ✅ تحديث حالة الحدث بنجاح
                $event->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'payment_id' => $paymentId,
                    'error_message' => null,
                ]);

            } catch (Throwable $e) {
                // 🐞 تسجيل الخطأ داخل الحدث لكي نراه في لوحة التحكم لاحقاً
                $event->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                // ❗ إعادة رمي الخطأ لكي يفهم نظام الـ Queue أن الـ Job فشل ويبدأ عملية الـ Retry
                throw $e;
            }
        });
    }

    /**
     * 💀 تستدعى تلقائياً إذا فشل الـ Job نهائياً بعد استنفاذ كل المحاولات الـ 5
     */
    public function failed(Throwable $exception): void
    {
        WebhookEvent::where('id', $this->webhookEventId)
            ->update([
                'status' => 'failed',
                'error_message' => 'FINAL FAIL: ' . $exception->getMessage(),
            ]);
    }
}
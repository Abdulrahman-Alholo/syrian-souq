<?php
namespace App\Modules\Payment\Contracts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Payment\Models\WebhookEvent;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use App\Jobs\ProcessPaymentWebhookJob; // 🟢 أفضل من استخدام Closure في الطوابير

class WebhookController extends Controller
{
    public function handleStripe(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        // 🔒 1. الحماية الأمنية الصارمة (Signature Verification)
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException | SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid payload or signature'], 400); // 💥 ركل الهاكر خارجاً
        }

        $eventId = $event->id;

        // 🔒 2. Idempotency (لمنع التكرار)
        if (WebhookEvent::where('event_id', $eventId)->where('gateway', 'stripe')->exists()) {
            return response()->json(['status' => 'ignored']);
        }

        $webhookRecord = WebhookEvent::create([
            'gateway' => 'stripe',
            'event_id' => $eventId,
            'event_type' => $event->type,
            'payload' => $event->toArray(),
        ]);

        // 🚀 3. إرسال المهمة للـ Queue باستخدام كلاس Job حقيقي (أنظف وأكثر استقراراً من הـ Closures)
        ProcessPaymentWebhookJob::dispatch('stripe', $webhookRecord->id)->onQueue('payments');

        // الرد السريع لـ Stripe لكي لا نقع في الـ Timeout
        return response()->json(['status' => 'ok']);
    }
}
<?php

namespace App\Modules\Payment\Contracts;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\DTOs\PaymentResult;

interface PaymentGateway
{
    // 🟢 يجب أن نلزم البوابات بإرجاع الـ DTO وليس مصفوفة عشوائية
    public function initiatePayment(Order $order, array $data = []): PaymentResult;
    public function handleWebhook(array $payload): void;
    // 🟢 الدالة الجديدة للاسترجاع
    public function refundPayment(string $transactionId, ?float $amount = null, ?string $reason = null): array;
}
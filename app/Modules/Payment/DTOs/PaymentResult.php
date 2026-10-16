<?php

namespace App\Modules\Payment\DTOs;

class PaymentResult
{
    public function __construct(
        public string $status,
        public ?string $redirectUrl = null,
        public ?string $transactionId = null,
        public array $meta = []
    ) {}
}
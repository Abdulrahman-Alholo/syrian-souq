<?php

namespace App\Modules\Payment\Factories;

use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\Gateways\StripeGateway;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    public static function make(string $gateway): PaymentGateway
    {
        return match ($gateway) {
            'stripe' => app(StripeGateway::class),
            default => throw new InvalidArgumentException("Unsupported gateway [$gateway]"),
        };
    }
}
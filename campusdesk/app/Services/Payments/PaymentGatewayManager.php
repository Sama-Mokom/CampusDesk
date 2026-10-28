<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: config('payments.default', 'fake');

        return match ($name) {
            'fake' => app(FakePaymentGateway::class),
            'fapshi' => app('App\Services\Payments\FapshiGateway'),
            'notchpay' => app('App\Services\Payments\NotchPayGateway'),
            default => throw new InvalidArgumentException("Unsupported payment driver [{$name}]."),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Payment;

final readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public ?string $clientSecret = null,
        public ?string $intentId = null,
        public ?string $error = null,
    ) {
    }
}

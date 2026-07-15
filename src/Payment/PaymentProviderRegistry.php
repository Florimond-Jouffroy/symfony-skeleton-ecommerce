<?php

declare(strict_types=1);

namespace App\Payment;

class PaymentProviderRegistry
{
    /** @param iterable<PaymentProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    public function getActive(): ?PaymentProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                return $provider;
            }
        }

        return null;
    }
}

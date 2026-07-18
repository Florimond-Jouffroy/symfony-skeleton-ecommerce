<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée lorsqu'une ligne de commande demande une quantité supérieure au stock
 * disponible (vérifié sous verrou pessimiste, donc anti-survente).
 */
class InsufficientStockException extends \RuntimeException
{
    public function __construct(public readonly string $label)
    {
        parent::__construct(sprintf('Stock insuffisant pour « %s ».', $label));
    }
}

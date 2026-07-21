<?php

declare(strict_types=1);

namespace App\Dto\Account;

use Symfony\Component\Validator\Constraints as Assert;

class ReturnRequestDto
{
    public string $orderNumber = '';

    #[Assert\NotBlank(message: 'Le motif du retour est obligatoire.', normalizer: 'trim')]
    public string $reason = '';

    /**
     * Lignes demandées : { orderItemId: int, quantity: int }.
     * La validation métier (articles valides, quantités retournables) reste dans
     * le controller, qui a accès à la commande et aux quantités déjà retournées.
     *
     * @var array<mixed>
     */
    public array $items = [];
}

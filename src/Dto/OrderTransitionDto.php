<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Order;
use Symfony\Component\Validator\Constraints as Assert;

class OrderTransitionDto
{
    /**
     * Statut cible. Seule l'appartenance à la liste des statuts est validée ici ;
     * l'autorisation de la transition depuis l'état courant (Order::TRANSITIONS)
     * dépend de la commande et reste dans le controller.
     */
    #[Assert\Choice(choices: Order::STATUSES, message: 'Statut invalide.')]
    public string $status = '';

    /** Commentaire optionnel joint à l'historique de statut. */
    public ?string $comment = null;
}

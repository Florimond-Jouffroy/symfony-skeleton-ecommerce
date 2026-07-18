<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ReturnItem;
use App\Entity\ReturnRequest;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;

/**
 * Gère le cycle de vie des demandes de retour (RMA).
 *
 * Au remboursement, le stock des articles retournés est réintégré (via
 * StockManager) et la commande est passée en "refunded" au mieux (best-effort :
 * ignoré si la machine à états de la commande ne l'autorise pas). Le versement
 * réel de l'argent n'est pas géré ici — c'est un point d'extension.
 */
class ReturnManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationLogManager $logManager,
        private readonly StockManager $stockManager,
        private readonly OrderManager $orderManager,
    ) {}

    /**
     * Crée une demande de retour pour une commande donnée.
     *
     * @param list<array{orderItem: OrderItem, quantity: int}> $lines
     */
    public function create(Order $order, Customer $customer, string $reason, array $lines): ?ReturnRequest
    {
        $return = new ReturnRequest();
        $return->setOrder($order);
        $return->setCustomer($customer);
        $return->setReason($reason);

        foreach ($lines as $line) {
            $item = new ReturnItem();
            $item->setOrderItem($line['orderItem']);
            $item->setQuantity($line['quantity']);
            $return->addItem($item);
            $this->em->persist($item);
        }

        $this->em->persist($return);

        return $this->flush() ? $return : null;
    }

    /**
     * Applique une transition de statut. Au passage en "refunded", réintègre le
     * stock et tente de passer la commande en "refunded".
     */
    public function transition(ReturnRequest $return, string $newStatus, ?string $adminNote = null): bool
    {
        if (!$return->canTransitionTo($newStatus)) {
            return false;
        }

        $return->setStatus($newStatus);
        if (null !== $adminNote) {
            $return->setAdminNote($adminNote);
        }

        if (ReturnRequest::STATUS_REFUNDED === $newStatus) {
            $this->stockManager->restoreForReturn($return);
            // Best-effort : delivered → refunded. Ignoré si déjà refunded (retour
            // partiel ultérieur) ou si l'état de la commande ne le permet pas.
            $this->orderManager->transition($return->getOrder(), Order::STATUS_REFUNDED, 'Retour remboursé.');
        }

        return $this->flush();
    }

    private function flush(): bool
    {
        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logManager->reportException($e);

            return false;
        }

        return true;
    }
}

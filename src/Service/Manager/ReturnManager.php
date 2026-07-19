<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ReturnItem;
use App\Entity\ReturnRequest;
use App\Repository\UserRepository;
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
    /**
     * Messages postés automatiquement dans le fil quand l'admin ne motive pas
     * lui-même la décision. Le refus n'y figure pas : son motif est obligatoire.
     *
     * @var array<string, string>
     */
    private const array STATUS_MESSAGES = [
        ReturnRequest::STATUS_APPROVED => 'Votre demande de retour a été acceptée. Vous pouvez nous renvoyer les articles concernés.',
        ReturnRequest::STATUS_REFUNDED => 'Votre retour a été remboursé. Le délai de réception dépend de votre banque.',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationLogManager $logManager,
        private readonly StockManager $stockManager,
        private readonly OrderManager $orderManager,
        private readonly SupportTicketManager $supportTicketManager,
        private readonly UserRepository $userRepository,
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

        if (!$this->flush()) {
            return null;
        }

        $this->openSupportTicket($return);

        return $return;
    }

    /**
     * Ouvre le fil de discussion de la demande : le motif du client en devient
     * le premier message, ce qui permet d'échanger avant de trancher.
     *
     * Best-effort et volontairement après le flush de la demande : un retour
     * enregistré ne doit jamais être perdu parce que le support a échoué.
     */
    private function openSupportTicket(ReturnRequest $return): void
    {
        $customer = $return->getCustomer();
        $user     = $this->userRepository->findOneBy(['email' => $customer->getEmail()]);

        $ticket = $this->supportTicketManager->open(
            $user,
            sprintf('Demande de retour — commande #%s', $return->getOrder()->getOrderNumber()),
            $return->getReason(),
            trim($customer->getFirstName().' '.$customer->getLastName()) ?: $customer->getEmail(),
            $customer->getEmail(),
        );

        if (null === $ticket) {
            return;
        }

        $return->setSupportTicket($ticket);
        $this->flush();
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

        $this->notifyStatusChange($return, $newStatus, $adminNote);

        if (ReturnRequest::STATUS_REFUNDED === $newStatus) {
            $this->stockManager->restoreForReturn($return);
            // Best-effort : delivered → refunded. Ignoré si déjà refunded (retour
            // partiel ultérieur) ou si l'état de la commande ne le permet pas.
            $this->orderManager->transition($return->getOrder(), Order::STATUS_REFUNDED, 'Retour remboursé.');
        }

        return $this->flush();
    }

    /**
     * Répercute le changement de statut dans le fil de discussion : la note de
     * l'admin si elle existe (cas du refus, où elle est obligatoire), sinon un
     * message de courtoisie pour que le client suive l'avancement.
     */
    private function notifyStatusChange(ReturnRequest $return, string $newStatus, ?string $adminNote): void
    {
        $ticket = $return->getSupportTicket();
        if (null === $ticket) {
            return;
        }

        $body = null !== $adminNote && '' !== trim($adminNote)
            ? trim($adminNote)
            : self::STATUS_MESSAGES[$newStatus] ?? null;

        if (null === $body) {
            return;
        }

        $this->supportTicketManager->addAdminMessage($ticket, $body);
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

<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\SupportMessage;
use App\Entity\SupportTicket;
use App\Entity\User;
use App\Service\SupportMailer;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gère les tickets de support et leurs messages.
 *
 * Centralise ce que les contrôleurs support faisaient en ligne, pour que
 * d'autres fonctionnalités puissent ouvrir un fil de discussion — les retours
 * (RMA) s'en servent pour transformer le motif du client en conversation.
 *
 * Les messages admin déclenchent une notification email au client (best-effort :
 * un échec d'envoi ne fait jamais échouer l'action métier).
 */
class SupportTicketManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SupportMailer $mailer,
        private readonly ApplicationLogManager $logManager,
        /** @var array<string, mixed> */
        #[Autowire(param: 'app.company')]
        private readonly array $company = [],
    ) {}

    /**
     * Ouvre un ticket avec son premier message.
     *
     * $user peut être null (commande passée sans compte) : le ticket bascule
     * alors en mode invité, consultable via son token.
     */
    public function open(
        ?User $user,
        string $subject,
        string $body,
        string $authorName,
        ?string $guestEmail = null,
    ): ?SupportTicket {
        $ticket = new SupportTicket();
        $ticket->setSubject($subject);

        if (null !== $user) {
            $ticket->setUser($user);
        } else {
            $ticket->setGuestName($authorName);
            $ticket->setGuestEmail($guestEmail);
        }

        $message = new SupportMessage();
        $message->setBody($body);
        $message->setIsFromAdmin(false);
        $message->setAuthorName($authorName);

        $ticket->addMessage($message);

        $this->em->persist($ticket);
        $this->em->persist($message);

        return $this->flush() ? $ticket : null;
    }

    /**
     * Message du client : rouvre le ticket, qui redevient à traiter.
     */
    public function addCustomerMessage(SupportTicket $ticket, string $body, string $authorName): ?SupportMessage
    {
        $message = $this->buildMessage($ticket, $body, false, $authorName);
        $ticket->setStatus(SupportTicket::STATUS_OPEN);

        return $this->flush() ? $message : null;
    }

    /**
     * Message de l'admin : le ticket passe en cours de traitement et le client
     * est notifié par email.
     */
    public function addAdminMessage(SupportTicket $ticket, string $body): ?SupportMessage
    {
        $message = $this->buildMessage($ticket, $body, true, (string) ($this->company['name'] ?? 'Admin'));
        $ticket->setStatus(SupportTicket::STATUS_IN_PROGRESS);

        if (!$this->flush()) {
            return null;
        }

        try {
            $this->mailer->sendReplyNotification($ticket, $message);
        } catch (\Throwable $e) {
            // Le message est enregistré : un échec d'envoi ne doit pas bloquer.
            $this->logManager->reportException($e);
        }

        return $message;
    }

    private function buildMessage(SupportTicket $ticket, string $body, bool $fromAdmin, string $authorName): SupportMessage
    {
        $message = new SupportMessage();
        $message->setBody($body);
        $message->setIsFromAdmin($fromAdmin);
        $message->setAuthorName($authorName);

        $ticket->addMessage($message);
        $ticket->touch();

        $this->em->persist($message);

        return $message;
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

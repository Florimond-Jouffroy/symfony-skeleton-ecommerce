<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SupportMessage;
use App\Entity\SupportTicket;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SupportMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $from,
        #[Autowire(param: 'app.company')]
        private readonly array $company,
    ) {}

    public function sendGuestConfirmation(SupportTicket $ticket): void
    {
        $trackingUrl = $this->urlGenerator->generate(
            'contact_suivi',
            ['token' => $ticket->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->from, $this->company['name']))
                ->to(new Address($ticket->getGuestEmail(), $ticket->getGuestName() ?? ''))
                ->subject('Votre demande a bien été reçue — '.$ticket->getSubject())
                ->htmlTemplate('emails/support_guest_confirmation.html.twig')
                ->context([
                    'ticket'      => $ticket,
                    'trackingUrl' => $trackingUrl,
                ]),
        );
    }

    public function sendReplyNotification(SupportTicket $ticket, SupportMessage $reply): void
    {
        $subject      = 'Réponse à votre demande — '.$ticket->getSubject();
        $contactName  = $ticket->getContactName();
        $contactEmail = $ticket->getContactEmail();

        if ($ticket->isGuest()) {
            $trackingUrl = $this->urlGenerator->generate(
                'contact_suivi',
                ['token' => $ticket->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } else {
            $trackingUrl = $this->urlGenerator->generate(
                'account_catchall',
                ['path' => 'support/'.$ticket->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->from, $this->company['name']))
                ->to(new Address($contactEmail, $contactName))
                ->subject($subject)
                ->htmlTemplate('emails/support_reply_notification.html.twig')
                ->context([
                    'ticket'      => $ticket,
                    'reply'       => $reply,
                    'trackingUrl' => $trackingUrl,
                ]),
        );
    }
}

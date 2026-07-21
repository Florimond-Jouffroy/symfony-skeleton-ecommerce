<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Contact\ContactDto;
use App\Dto\Contact\ContactReplyDto;
use App\Entity\SupportMessage;
use App\Entity\SupportTicket;
use App\Repository\SupportTicketRepository;
use App\Service\SupportMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/contact')]
class ContactController extends AbstractController
{
    private function serializeTicket(SupportTicket $t): array
    {
        return [
            'id'        => $t->getId(),
            'subject'   => $t->getSubject(),
            'status'    => $t->getStatus(),
            'guestName' => $t->getGuestName(),
            'createdAt' => $t->getCreatedAt()->format('Y-m-d H:i'),
            'updatedAt' => $t->getUpdatedAt()->format('Y-m-d H:i'),
            'messages'  => array_map(
                fn (SupportMessage $m) => [
                    'id'          => $m->getId(),
                    'body'        => $m->getBody(),
                    'isFromAdmin' => $m->isFromAdmin(),
                    'authorName'  => $m->getAuthorName(),
                    'createdAt'   => $m->getCreatedAt()->format('Y-m-d H:i'),
                ],
                $t->getMessages()->toArray()
            ),
        ];
    }

    #[Route('/ticket', name: 'api_contact_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] ContactDto $dto,
        EntityManagerInterface $em,
        SupportMailer $mailer,
    ): JsonResponse {
        $name    = trim($dto->name);
        $email   = trim($dto->email);
        $subject = trim($dto->subject);
        $body    = trim($dto->body);

        $ticket = new SupportTicket();
        $ticket->setSubject($subject);
        $ticket->setGuestName($name);
        $ticket->setGuestEmail($email);

        $message = new SupportMessage();
        $message->setBody($body);
        $message->setIsFromAdmin(false);
        $message->setAuthorName($name);

        $ticket->addMessage($message);
        $em->persist($ticket);
        $em->flush();

        try {
            $mailer->sendGuestConfirmation($ticket);
        } catch (\Throwable) {
            // Ne pas bloquer si l'envoi échoue
        }

        return $this->json(['token' => $ticket->getToken()], Response::HTTP_CREATED);
    }

    #[Route('/suivi', name: 'api_contact_suivi', methods: ['GET'])]
    public function suivi(Request $request, SupportTicketRepository $repo): JsonResponse
    {
        $token  = (string) $request->query->get('token', '');
        $ticket = $repo->findByToken($token);

        if (!$ticket || !$ticket->isGuest()) {
            return $this->json(['message' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeTicket($ticket));
    }

    #[Route('/suivi/repondre', name: 'api_contact_suivi_reply', methods: ['POST'])]
    public function reply(#[MapRequestPayload] ContactReplyDto $dto, SupportTicketRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $ticket = $repo->findByToken(trim($dto->token));

        if (!$ticket || !$ticket->isGuest()) {
            return $this->json(['message' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (SupportTicket::STATUS_CLOSED === $ticket->getStatus()) {
            return $this->json(['message' => 'Ce ticket est fermé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = new SupportMessage();
        $message->setBody(trim($dto->body));
        $message->setIsFromAdmin(false);
        $message->setAuthorName($ticket->getGuestName() ?? 'Invité');

        $ticket->addMessage($message);
        $ticket->setStatus(SupportTicket::STATUS_OPEN);
        $ticket->touch();
        $em->flush();

        return $this->json($this->serializeTicket($ticket));
    }
}

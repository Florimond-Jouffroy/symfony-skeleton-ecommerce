<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\SupportMessage;
use App\Entity\SupportTicket;
use App\Repository\SupportTicketRepository;
use App\Service\SupportMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
        Request $request,
        EntityManagerInterface $em,
        SupportMailer $mailer,
        ValidatorInterface $validator,
    ): JsonResponse {
        $data = $request->toArray();

        $name    = trim((string) ($data['name'] ?? ''));
        $email   = trim((string) ($data['email'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $body    = trim((string) ($data['body'] ?? ''));

        $errors = [];
        if ('' === $name)    $errors[] = 'Le nom est requis.';
        if ('' === $subject) $errors[] = 'Le sujet est requis.';
        if ('' === $body)    $errors[] = 'Le message est requis.';

        $emailViolations = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);
        if (count($emailViolations) > 0) {
            $errors[] = 'Adresse e-mail invalide.';
        }

        if (!empty($errors)) {
            return $this->json(['message' => implode(' ', $errors)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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
    public function reply(Request $request, SupportTicketRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $data   = $request->toArray();
        $token  = trim((string) ($data['token'] ?? ''));
        $body   = trim((string) ($data['body'] ?? ''));
        $ticket = $repo->findByToken($token);

        if (!$ticket || !$ticket->isGuest()) {
            return $this->json(['message' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($ticket->getStatus() === SupportTicket::STATUS_CLOSED) {
            return $this->json(['message' => 'Ce ticket est fermé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ('' === $body) {
            return $this->json(['message' => 'Le message ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = new SupportMessage();
        $message->setBody($body);
        $message->setIsFromAdmin(false);
        $message->setAuthorName($ticket->getGuestName() ?? 'Invité');

        $ticket->addMessage($message);
        $ticket->setStatus(SupportTicket::STATUS_OPEN);
        $ticket->touch();
        $em->flush();

        return $this->json($this->serializeTicket($ticket));
    }
}

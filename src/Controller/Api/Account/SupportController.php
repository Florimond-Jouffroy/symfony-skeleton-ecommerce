<?php

declare(strict_types=1);

namespace App\Controller\Api\Account;

use App\Entity\SupportMessage;
use App\Entity\SupportTicket;
use App\Repository\SupportTicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/account/support')]
#[IsGranted('ROLE_USER')]
class SupportController extends AbstractController
{
    private function serializeList(SupportTicket $t): array
    {
        return [
            'id'           => $t->getId(),
            'subject'      => $t->getSubject(),
            'status'       => $t->getStatus(),
            'createdAt'    => $t->getCreatedAt()->format('Y-m-d H:i'),
            'updatedAt'    => $t->getUpdatedAt()->format('Y-m-d H:i'),
            'messageCount' => $t->getMessages()->count(),
        ];
    }

    private function serializeDetail(SupportTicket $t): array
    {
        return [
            ...$this->serializeList($t),
            'messages' => array_map(
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

    #[Route('', name: 'api_account_support_list', methods: ['GET'])]
    public function list(SupportTicketRepository $repo): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->json(array_map($this->serializeList(...), $repo->findByUser($user)));
    }

    #[Route('', name: 'api_account_support_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $data = $request->toArray();

        $subject = trim((string) ($data['subject'] ?? ''));
        $body    = trim((string) ($data['body'] ?? ''));

        if ('' === $subject || '' === $body) {
            return $this->json(['message' => 'Le sujet et le message sont requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ticket = new SupportTicket();
        $ticket->setSubject($subject);
        $ticket->setUser($user);

        $message = new SupportMessage();
        $message->setBody($body);
        $message->setIsFromAdmin(false);
        $message->setAuthorName($user->getEmail());

        $ticket->addMessage($message);
        $em->persist($ticket);
        $em->flush();

        return $this->json($this->serializeDetail($ticket), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_account_support_detail', methods: ['GET'])]
    public function detail(int $id, SupportTicketRepository $repo): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $ticket = $repo->find($id);

        if (!$ticket || $ticket->getUser() !== $user) {
            return $this->json(['message' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeDetail($ticket));
    }

    #[Route('/{id}/repondre', name: 'api_account_support_reply', methods: ['POST'])]
    public function reply(int $id, Request $request, SupportTicketRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $ticket = $repo->find($id);

        if (!$ticket || $ticket->getUser() !== $user) {
            return $this->json(['message' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($ticket->getStatus() === SupportTicket::STATUS_CLOSED) {
            return $this->json(['message' => 'Ce ticket est fermé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $body = trim((string) ($request->toArray()['body'] ?? ''));
        if ('' === $body) {
            return $this->json(['message' => 'Le message ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = new SupportMessage();
        $message->setBody($body);
        $message->setIsFromAdmin(false);
        $message->setAuthorName($user->getEmail());

        $ticket->addMessage($message);
        $ticket->setStatus(SupportTicket::STATUS_OPEN);
        $ticket->touch();
        $em->flush();

        return $this->json($this->serializeDetail($ticket));
    }
}

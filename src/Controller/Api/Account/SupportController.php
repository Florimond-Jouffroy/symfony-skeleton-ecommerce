<?php

declare(strict_types=1);

namespace App\Controller\Api\Account;

use App\Dto\SupportReplyDto;
use App\Dto\SupportTicketDto;
use App\Entity\SupportMessage;
use App\Entity\SupportTicket;
use App\Repository\SupportTicketRepository;
use App\Service\Manager\SupportTicketManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/account/support')]
#[IsGranted('ROLE_USER')]
class SupportController extends AbstractController
{
    public function __construct(
        private readonly SupportTicketManager $ticketManager,
    ) {}

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
    public function create(#[MapRequestPayload] SupportTicketDto $dto): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ticket = $this->ticketManager->open($user, trim($dto->subject), trim($dto->body), (string) $user->getEmail());

        if (null === $ticket) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

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
    public function reply(int $id, #[MapRequestPayload] SupportReplyDto $dto, SupportTicketRepository $repo): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $ticket = $repo->find($id);

        if (!$ticket || $ticket->getUser() !== $user) {
            return $this->json(['message' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (SupportTicket::STATUS_CLOSED === $ticket->getStatus()) {
            return $this->json(['message' => 'Ce ticket est fermé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->ticketManager->addCustomerMessage($ticket, trim($dto->body), (string) $user->getEmail());

        return $this->json($this->serializeDetail($ticket));
    }
}

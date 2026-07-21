<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\SupportReplyDto;
use App\Dto\SupportStatusDto;
use App\Entity\SupportMessage;
use App\Entity\SupportTicket;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\SupportTicketRepository;
use App\Security\Voter\SupportVoter;
use App\Service\ActivityLogger;
use App\Service\Manager\SupportTicketManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/support')]
class SupportController extends AbstractController
{
    public function __construct(
        private readonly SupportTicketManager $ticketManager,
    ) {}

    private function serializeList(SupportTicket $t): array
    {
        $messages = $t->getMessages();

        return [
            'id'           => $t->getId(),
            'subject'      => $t->getSubject(),
            'status'       => $t->getStatus(),
            'isGuest'      => $t->isGuest(),
            'contactName'  => $t->getContactName(),
            'contactEmail' => $t->getContactEmail(),
            'createdAt'    => $t->getCreatedAt()->format('Y-m-d H:i'),
            'updatedAt'    => $t->getUpdatedAt()->format('Y-m-d H:i'),
            'messageCount' => $messages->count(),
        ];
    }

    private function serializeDetail(SupportTicket $t, array $recentOrders = [], array $otherTickets = []): array
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
            'recentOrders' => array_map(
                fn ($o) => [
                    'id'          => $o->getId(),
                    'orderNumber' => $o->getOrderNumber(),
                    'status'      => $o->getStatus(),
                    'total'       => $o->getTotal(),
                    'createdAt'   => $o->getCreatedAt()->format('Y-m-d'),
                ],
                $recentOrders
            ),
            'otherTickets' => array_map(
                fn (SupportTicket $ot) => [
                    'id'        => $ot->getId(),
                    'subject'   => $ot->getSubject(),
                    'status'    => $ot->getStatus(),
                    'updatedAt' => $ot->getUpdatedAt()->format('Y-m-d'),
                ],
                $otherTickets
            ),
        ];
    }

    private function fetchRecentOrders(SupportTicket $ticket, CustomerRepository $customerRepo, OrderRepository $orderRepo): array
    {
        if ($ticket->isGuest()) {
            return [];
        }
        $customer = $customerRepo->findByEmail($ticket->getUser()->getEmail());
        if (!$customer) {
            return [];
        }

        return $orderRepo->findRecentByCustomer($customer->getId());
    }

    private function fetchOtherTickets(SupportTicket $ticket, SupportTicketRepository $repo): array
    {
        if ($ticket->isGuest()) {
            return $repo->findOtherByGuestEmail($ticket->getGuestEmail() ?? '', $ticket->getId());
        }

        return $repo->findOtherByUser($ticket->getUser(), $ticket->getId());
    }

    #[Route('', name: 'api_admin_support_list', methods: ['GET'])]
    public function list(Request $request, SupportTicketRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted(SupportVoter::VIEW);

        $status = $request->query->get('status');
        $search = $request->query->get('search');

        $tickets = $repo->findForAdmin($status ?: null, $search ?: null);

        return $this->json(array_map($this->serializeList(...), $tickets));
    }

    #[Route('/{id}', name: 'api_admin_support_detail', methods: ['GET'])]
    public function detail(int $id, SupportTicketRepository $repo, CustomerRepository $customerRepo, OrderRepository $orderRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted(SupportVoter::VIEW);

        $ticket = $repo->find($id);
        if (!$ticket) {
            return $this->json(['message' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeDetail($ticket, $this->fetchRecentOrders($ticket, $customerRepo, $orderRepo), $this->fetchOtherTickets($ticket, $repo)));
    }

    #[Route('/{id}/repondre', name: 'api_admin_support_reply', methods: ['POST'])]
    public function reply(
        int $id,
        #[MapRequestPayload] SupportReplyDto $dto,
        SupportTicketRepository $repo,
        CustomerRepository $customerRepo,
        OrderRepository $orderRepo,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(SupportVoter::REPLY);

        $ticket = $repo->find($id);
        if (!$ticket) {
            return $this->json(['message' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $this->ticketManager->addAdminMessage($ticket, trim($dto->body));

        return $this->json($this->serializeDetail($ticket, $this->fetchRecentOrders($ticket, $customerRepo, $orderRepo), $this->fetchOtherTickets($ticket, $repo)));
    }

    #[Route('/{id}/statut', name: 'api_admin_support_status', methods: ['PATCH'])]
    public function updateStatus(int $id, #[MapRequestPayload] SupportStatusDto $dto, SupportTicketRepository $repo, EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
    {
        $this->denyAccessUnlessGranted(SupportVoter::EDIT);

        $ticket = $repo->find($id);
        if (!$ticket) {
            return $this->json(['message' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $previousStatus = $ticket->getStatus();
        $ticket->setStatus($dto->status);
        $ticket->touch();
        $em->flush();

        $activityLogger->log(
            'ticket.status_changed',
            'ticket',
            $ticket->getId(),
            $ticket->getSubject(),
            ['from' => $previousStatus, 'to' => $dto->status],
        );

        return $this->json($this->serializeDetail($ticket));
    }
}

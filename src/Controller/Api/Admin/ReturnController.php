<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\ReturnStatusDto;
use App\Dto\SupportReplyDto;
use App\Entity\ReturnRequest;
use App\Entity\SupportMessage;
use App\Repository\ReturnRequestRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ReturnVoter;
use App\Service\Manager\ReturnManager;
use App\Service\Manager\SupportTicketManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/retours')]
class ReturnController extends AbstractController
{
    /**
     * Cache email => id utilisateur, pour éviter une requête par retour sur la liste.
     *
     * @var array<string, int|null>
     */
    private array $userIdByEmail = [];

    public function __construct(
        private readonly ReturnRequestRepository $returnRepository,
        private readonly ReturnManager $returnManager,
        private readonly UserRepository $userRepository,
        private readonly SupportTicketManager $supportTicketManager,
    ) {}

    #[Route('', name: 'api_admin_returns_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted(ReturnVoter::VIEW);

        return $this->json([
            'items' => array_map($this->serialize(...), $this->returnRepository->findAllOrdered()),
        ]);
    }

    #[Route('/{id}', name: 'api_admin_returns_get', methods: ['GET'])]
    public function get(ReturnRequest $return): JsonResponse
    {
        $this->denyAccessUnlessGranted(ReturnVoter::VIEW);

        return $this->json($this->serialize($return));
    }

    #[Route('/{id}/statut', name: 'api_admin_returns_status', methods: ['PATCH'])]
    public function updateStatus(ReturnRequest $return, #[MapRequestPayload] ReturnStatusDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(ReturnVoter::EDIT);

        $adminNote = null !== $dto->adminNote ? (trim($dto->adminNote) ?: null) : null;

        if (!$this->returnManager->transition($return, $dto->status, $adminNote)) {
            return $this->json(['message' => 'Transition impossible depuis le statut actuel.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serialize($return));
    }

    /**
     * Répond au client dans le fil de discussion de la demande, sans quitter la
     * page Retours. Le droit d'édition des retours suffit : pas besoin des
     * permissions du module Support.
     */
    #[Route('/{id}/message', name: 'api_admin_returns_message', methods: ['POST'])]
    public function reply(ReturnRequest $return, #[MapRequestPayload] SupportReplyDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(ReturnVoter::EDIT);

        $ticket = $return->getSupportTicket();
        if (null === $ticket) {
            return $this->json(['message' => 'Cette demande n\'a pas de fil de discussion.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null === $this->supportTicketManager->addAdminMessage($ticket, trim($dto->body))) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($return));
    }

    /**
     * Id de l'utilisateur correspondant à ce client, pour lier la demande à sa
     * fiche. Null si la commande a été passée sans compte (rapprochement par email).
     */
    private function findUserId(string $email): ?int
    {
        if (!array_key_exists($email, $this->userIdByEmail)) {
            $this->userIdByEmail[$email] = $this->userRepository->findOneBy(['email' => $email])?->getId();
        }

        return $this->userIdByEmail[$email];
    }

    /** @return array<string, mixed> */
    private function serialize(ReturnRequest $return): array
    {
        $customer = $return->getCustomer();
        $items    = array_map(
            static function ($item) {
                $orderItem = $item->getOrderItem();
                $product   = $orderItem->getProduct();
                $imageUrl  = null;
                if (null !== $product) {
                    foreach ($product->getImages() as $img) {
                        $imageUrl = $img->getUrl();
                        break;
                    }
                }

                return [
                    'productName' => $orderItem->getProductName(),
                    'variantName' => $orderItem->getVariantName(),
                    'imageUrl'    => $imageUrl,
                    'unitPrice'   => $orderItem->getUnitPrice(),
                    'quantity'    => $item->getQuantity(),
                ];
            },
            $return->getItems()->toArray(),
        );

        return [
            'id'             => $return->getId(),
            'orderId'        => $return->getOrder()->getId(),
            'orderNumber'    => $return->getOrder()->getOrderNumber(),
            'customerName'   => trim($customer->getFirstName().' '.$customer->getLastName()),
            'customerEmail'  => $customer->getEmail(),
            'customerUserId' => $this->findUserId($customer->getEmail()),
            'status'         => $return->getStatus(),
            'reason'         => $return->getReason(),
            'adminNote'      => $return->getAdminNote(),
            'items'          => array_values($items),
            'support'        => $this->serializeTicket($return),
            'createdAt'      => $return->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'      => $return->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Fil de discussion de la demande. Null pour les demandes antérieures à
     * cette fonctionnalité.
     *
     * @return array<string, mixed>|null
     */
    private function serializeTicket(ReturnRequest $return): ?array
    {
        $ticket = $return->getSupportTicket();
        if (null === $ticket) {
            return null;
        }

        return [
            'ticketId' => $ticket->getId(),
            'status'   => $ticket->getStatus(),
            'messages' => array_map(
                static fn (SupportMessage $m) => [
                    'id'          => $m->getId(),
                    'body'        => $m->getBody(),
                    'isFromAdmin' => $m->isFromAdmin(),
                    'authorName'  => $m->getAuthorName(),
                    'createdAt'   => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $ticket->getMessages()->toArray(),
            ),
        ];
    }
}

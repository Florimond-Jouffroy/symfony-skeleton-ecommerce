<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\ReturnRequest;
use App\Repository\ReturnRequestRepository;
use App\Security\Voter\ReturnVoter;
use App\Service\Manager\ReturnManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/retours')]
class ReturnController extends AbstractController
{
    public function __construct(
        private readonly ReturnRequestRepository $returnRepository,
        private readonly ReturnManager $returnManager,
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
    public function updateStatus(ReturnRequest $return, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ReturnVoter::EDIT);

        $payload   = $request->toArray();
        $status    = trim((string) ($payload['status'] ?? ''));
        $adminNote = isset($payload['adminNote']) ? trim((string) $payload['adminNote']) : null;

        $allowed = [ReturnRequest::STATUS_APPROVED, ReturnRequest::STATUS_REJECTED, ReturnRequest::STATUS_REFUNDED];
        if (!in_array($status, $allowed, true)) {
            return $this->json(['message' => 'Statut invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->returnManager->transition($return, $status, $adminNote)) {
            return $this->json(['message' => 'Transition impossible depuis le statut actuel.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serialize($return));
    }

    /** @return array<string, mixed> */
    private function serialize(ReturnRequest $return): array
    {
        $customer = $return->getCustomer();
        $items    = array_map(
            static fn ($item) => [
                'productName' => $item->getOrderItem()->getProductName(),
                'variantName' => $item->getOrderItem()->getVariantName(),
                'unitPrice'   => $item->getOrderItem()->getUnitPrice(),
                'quantity'    => $item->getQuantity(),
            ],
            $return->getItems()->toArray(),
        );

        return [
            'id'            => $return->getId(),
            'orderNumber'   => $return->getOrder()->getOrderNumber(),
            'customerName'  => trim($customer->getFirstName().' '.$customer->getLastName()),
            'customerEmail' => $customer->getEmail(),
            'status'        => $return->getStatus(),
            'reason'        => $return->getReason(),
            'adminNote'     => $return->getAdminNote(),
            'items'         => array_values($items),
            'createdAt'     => $return->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'     => $return->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

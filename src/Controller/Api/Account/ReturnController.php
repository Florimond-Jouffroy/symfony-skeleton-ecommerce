<?php

declare(strict_types=1);

namespace App\Controller\Api\Account;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ReturnRequest;
use App\Repository\AppSettingRepository;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ReturnRequestRepository;
use App\Service\Manager\ReturnManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/compte/retours')]
class ReturnController extends AbstractController
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly OrderRepository $orderRepository,
        private readonly ReturnRequestRepository $returnRepository,
        private readonly AppSettingRepository $settingRepo,
        private readonly ReturnManager $returnManager,
    ) {}

    #[Route('', name: 'api_account_returns_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $customer = $this->customerRepository->findOneBy(['email' => $user->getEmail()]);

        if (null === $customer) {
            return $this->json(['items' => []]);
        }

        return $this->json([
            'items' => array_map($this->serialize(...), $this->returnRepository->findByCustomer((int) $customer->getId())),
        ]);
    }

    #[Route('', name: 'api_account_returns_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if ('true' !== $this->settingRepo->getValue('returns.enabled', 'false')) {
            return $this->json(['message' => 'Les retours ne sont pas activés.'], Response::HTTP_FORBIDDEN);
        }

        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $customer = $this->customerRepository->findOneBy(['email' => $user->getEmail()]);
        if (null === $customer) {
            return $this->json(['message' => 'Aucune commande à retourner.'], Response::HTTP_NOT_FOUND);
        }

        $payload     = $request->toArray();
        $orderNumber = trim((string) ($payload['orderNumber'] ?? ''));
        $reason      = trim((string) ($payload['reason'] ?? ''));

        if ('' === $reason) {
            return $this->json(['message' => 'Le motif du retour est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if (null === $order || $order->getCustomer()->getId() !== $customer->getId()) {
            return $this->json(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (Order::STATUS_DELIVERED !== $order->getStatus()) {
            return $this->json(['message' => 'Seules les commandes livrées peuvent faire l\'objet d\'un retour.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Indexe les lignes de la commande par id pour valider les articles demandés.
        $orderItemsById = [];
        foreach ($order->getItems() as $orderItem) {
            $orderItemsById[$orderItem->getId()] = $orderItem;
        }

        // Quantités déjà retournées (retours non-refusés) : on ne peut pas retourner
        // plus que le restant, même en cumulant plusieurs demandes.
        $returnedMap = $this->returnRepository->getReturnedQuantitiesForOrder((int) $order->getId());

        $lines    = [];
        $rawItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        foreach ($rawItems as $raw) {
            $itemId    = (int) ($raw['orderItemId'] ?? 0);
            $quantity  = (int) ($raw['quantity'] ?? 0);
            $orderItem = $orderItemsById[$itemId] ?? null;

            if (!$orderItem instanceof OrderItem || $quantity < 1) {
                continue;
            }

            $remaining = $orderItem->getQuantity() - ($returnedMap[$itemId] ?? 0);
            if ($quantity > $remaining) {
                return $this->json(['message' => 'Quantité de retour supérieure à la quantité retournable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $lines[] = ['orderItem' => $orderItem, 'quantity' => $quantity];
        }

        if ([] === $lines) {
            return $this->json(['message' => 'Sélectionnez au moins un article à retourner.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $return = $this->returnManager->create($order, $customer, $reason, $lines);
        if (null === $return) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($return), Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function serialize(ReturnRequest $return): array
    {
        $items = array_map(
            static fn ($item) => [
                'productName' => $item->getOrderItem()->getProductName(),
                'variantName' => $item->getOrderItem()->getVariantName(),
                'quantity'    => $item->getQuantity(),
            ],
            $return->getItems()->toArray(),
        );

        return [
            'id'          => $return->getId(),
            'orderNumber' => $return->getOrder()->getOrderNumber(),
            'status'      => $return->getStatus(),
            'reason'      => $return->getReason(),
            'items'       => array_values($items),
            'createdAt'   => $return->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

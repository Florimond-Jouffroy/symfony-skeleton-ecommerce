<?php

declare(strict_types=1);

namespace App\Controller\Api\Account;

use App\Entity\Order;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ReturnRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/compte/commandes')]
class OrderController extends AbstractController
{
    #[Route('', name: 'api_account_orders_list', methods: ['GET'])]
    public function list(
        Request $request,
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $customer = $customerRepository->findOneBy(['email' => $user->getEmail()]);

        if (null === $customer) {
            return $this->json(['items' => [], 'total' => 0, 'page' => 1, 'pageSize' => 10]);
        }

        $page     = max(1, $request->query->getInt('page', 1));
        $pageSize = min(20, max(1, $request->query->getInt('pageSize', 10)));

        $result = $orderRepository->searchPaginated(null, $page, $pageSize, null, $customer->getId());

        return $this->json([
            'items'    => array_map($this->serializeList(...), $result['items']),
            'total'    => $result['total'],
            'page'     => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('/{number}', name: 'api_account_orders_get', methods: ['GET'])]
    public function get(
        string $number,
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        ReturnRequestRepository $returnRepository,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $customer = $customerRepository->findOneBy(['email' => $user->getEmail()]);

        if (null === $customer) {
            return $this->json(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $order = $orderRepository->findByOrderNumber($number);

        if (null === $order || $order->getCustomer()->getId() !== $customer->getId()) {
            return $this->json(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $returnedMap = $returnRepository->getReturnedQuantitiesForOrder((int) $order->getId());

        return $this->json($this->serializeFull($order, $returnedMap));
    }

    private function serializeList(Order $order): array
    {
        return [
            'id'          => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'status'      => $order->getStatus(),
            'total'       => $order->getTotal(),
            'itemCount'   => $order->getItems()->count(),
            'createdAt'   => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<int, int> $returnedMap orderItemId => quantité déjà retournée
     */
    private function serializeFull(Order $order, array $returnedMap = []): array
    {
        $items = array_map(
            static function ($item) use ($returnedMap) {
                $product  = $item->getProduct();
                $imageUrl = null;
                if (null !== $product) {
                    foreach ($product->getImages() as $img) {
                        $imageUrl = $img->getUrl();
                        break;
                    }
                }

                $returned = $returnedMap[$item->getId()] ?? 0;

                return [
                    'id'                 => $item->getId(),
                    'productName'        => $item->getProductName(),
                    'variantName'        => $item->getVariantName(),
                    'imageUrl'           => $imageUrl,
                    'unitPrice'          => $item->getUnitPrice(),
                    'quantity'           => $item->getQuantity(),
                    'returnableQuantity' => max(0, $item->getQuantity() - $returned),
                    'total'              => $item->getTotal(),
                ];
            },
            $order->getItems()->toArray(),
        );

        return array_merge($this->serializeList($order), [
            'subtotal'        => $order->getSubtotal(),
            'discountAmount'  => $order->getDiscountAmount(),
            'shippingAmount'  => $order->getShippingAmount(),
            'shippingAddress' => $order->getShippingAddress(),
            'customerNote'    => $order->getCustomerNote(),
            'items'           => array_values($items),
        ]);
    }
}

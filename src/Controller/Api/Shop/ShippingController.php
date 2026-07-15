<?php

declare(strict_types=1);

namespace App\Controller\Api\Shop;

use App\Repository\ShippingMethodRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/boutique/livraison')]
class ShippingController extends AbstractController
{
    /**
     * Returns active shipping methods with the effective price for the given cart subtotal.
     * Pass ?subtotal=XXXX (in cents) to get the correct price after free-shipping threshold check.
     */
    #[Route('', name: 'api_shop_shipping_list', methods: ['GET'])]
    public function list(Request $request, ShippingMethodRepository $repo): JsonResponse
    {
        $subtotal = max(0, $request->query->getInt('subtotal', 0));
        $methods  = $repo->findActive();

        return $this->json(array_map(
            fn ($m) => [
                'id'              => $m->getId(),
                'name'            => $m->getName(),
                'description'     => $m->getDescription(),
                'price'           => $m->getPrice(),
                'effectivePrice'  => $m->getEffectivePrice($subtotal),
                'freeAboveAmount' => $m->getFreeAboveAmount(),
            ],
            $methods,
        ));
    }
}

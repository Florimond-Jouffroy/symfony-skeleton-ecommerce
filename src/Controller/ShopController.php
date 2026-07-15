<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    #[Route('/boutique', name: 'shop_index')]
    #[Route('/boutique/{path}', name: 'shop_catchall', requirements: ['path' => '.+'])]
    public function index(): Response
    {
        return $this->render('shop/shell.html.twig', [
            'urls' => [
                'products'      => $this->generateUrl('api_shop_products_list'),
                'categories'    => $this->generateUrl('api_shop_categories_list'),
                'cart'          => $this->generateUrl('api_shop_cart_get'),
                'shipping'      => $this->generateUrl('api_shop_shipping_list'),
                'checkout'      => $this->generateUrl('api_shop_checkout'),
                'promo'         => $this->generateUrl('api_shop_promo_apply'),
                'profile'       => $this->generateUrl('api_account_profile_get'),
                'reviewSubmit'  => $this->generateUrl('api_account_reviews_create'),
            ],
            'isConnected' => $this->getUser() !== null,
        ]);
    }
}

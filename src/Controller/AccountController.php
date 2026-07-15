<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'account_index')]
    #[Route('/mon-compte/{path}', name: 'account_catchall', requirements: ['path' => '.+'])]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->render('account/shell.html.twig', [
            'urls' => [
                'profile'        => $this->generateUrl('api_account_profile_get'),
                'profileUpdate'  => $this->generateUrl('api_account_profile_update'),
                'passwordUpdate' => $this->generateUrl('api_account_password_update'),
                'orders'         => $this->generateUrl('api_account_orders_list'),
                'support'        => $this->generateUrl('api_account_support_list'),
                'reviews'        => $this->generateUrl('api_account_reviews_list'),
                'logout'         => $this->generateUrl('app_security_logout'),
            ],
            'userEmail' => $user->getEmail(),
        ]);
    }
}

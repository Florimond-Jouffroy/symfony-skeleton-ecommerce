<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact_index')]
    public function index(): Response
    {
        /** @var \App\Entity\User|null $user */
        $user        = $this->getUser();
        $isConnected = $user !== null;

        return $this->render('contact/shell.html.twig', [
            'isConnected'  => $isConnected,
            'prefillEmail' => $isConnected ? $user->getEmail() : '',
            'urls' => [
                'createTicket'   => $this->generateUrl('api_contact_create'),
                'createTicketAuth' => $this->generateUrl('api_account_support_create'),
                'suivi'          => $this->generateUrl('api_contact_suivi'),
                'suiviReply'     => $this->generateUrl('api_contact_suivi_reply'),
            ],
        ]);
    }

    #[Route('/contact/suivi', name: 'contact_suivi')]
    public function suivi(): Response
    {
        return $this->render('contact/shell.html.twig', [
            'isConnected'  => false,
            'prefillEmail' => '',
            'urls' => [
                'createTicket'     => $this->generateUrl('api_contact_create'),
                'createTicketAuth' => $this->generateUrl('api_account_support_create'),
                'suivi'            => $this->generateUrl('api_contact_suivi'),
                'suiviReply'       => $this->generateUrl('api_contact_suivi_reply'),
            ],
        ]);
    }
}

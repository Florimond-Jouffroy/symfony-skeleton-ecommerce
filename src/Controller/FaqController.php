<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FaqController extends AbstractController
{
    #[Route('/faq', name: 'faq_index')]
    public function index(): Response
    {
        return $this->render('faq/shell.html.twig', [
            'urls' => [
                'faq' => $this->generateUrl('api_faq_list'),
            ],
        ]);
    }
}

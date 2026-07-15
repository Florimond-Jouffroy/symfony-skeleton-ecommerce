<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\StaticPageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StaticPageController extends AbstractController
{
    #[Route('/pages/{slug}', name: 'static_page_show')]
    public function show(string $slug, StaticPageRepository $repo): Response
    {
        $page = $repo->findBySlug($slug);

        if (!$page || !$page->isActive()) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        return $this->render('pages/show.html.twig', [
            'page' => $page,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    #[Route('/blog', name: 'blog_index')]
    #[Route('/blog/{path}', name: 'blog_catchall', requirements: ['path' => '.+'])]
    public function index(): Response
    {
        return $this->render('blog/shell.html.twig', [
            'urls' => [
                'articles'   => $this->generateUrl('api_blog_articles_list'),
                'categories' => $this->generateUrl('api_blog_categories_list'),
            ],
        ]);
    }
}

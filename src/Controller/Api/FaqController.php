<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\FaqItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class FaqController extends AbstractController
{
    #[Route('/api/faq', name: 'api_faq_list', methods: ['GET'])]
    public function list(FaqItemRepository $repo): JsonResponse
    {
        $items = array_map(
            fn ($item) => [
                'id'       => $item->getId(),
                'question' => $item->getQuestion(),
                'answer'   => $item->getAnswer(),
            ],
            $repo->findActiveOrdered()
        );

        return $this->json($items);
    }
}

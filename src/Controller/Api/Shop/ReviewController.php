<?php

declare(strict_types=1);

namespace App\Controller\Api\Shop;

use App\Entity\ProductReview;
use App\Repository\ProductRepository;
use App\Repository\ProductReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/boutique/produits/{slug}/avis')]
class ReviewController extends AbstractController
{
    #[Route('', name: 'api_shop_product_reviews', methods: ['GET'])]
    public function list(
        string $slug,
        ProductRepository $productRepo,
        ProductReviewRepository $reviewRepo,
    ): JsonResponse {
        $product = $productRepo->findOneBy(['slug' => $slug]);
        if (!$product) {
            return $this->json(['message' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $stats   = $reviewRepo->getProductStats($product->getId());
        $reviews = $reviewRepo->findApprovedByProduct($product->getId());

        return $this->json([
            'avgRating' => $stats['avgRating'],
            'count'     => $stats['count'],
            'reviews'   => array_map(static fn (ProductReview $r) => [
                'id'         => $r->getId(),
                'authorName' => $r->getAuthorName(),
                'rating'     => $r->getRating(),
                'comment'    => $r->getComment(),
                'createdAt'  => $r->getCreatedAt()->format('Y-m-d'),
            ], $reviews),
        ]);
    }
}

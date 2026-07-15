<?php

declare(strict_types=1);

namespace App\Controller\Api\Account;

use App\Entity\ProductReview;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/compte/avis')]
#[IsGranted('ROLE_USER')]
class ReviewController extends AbstractController
{
    private function serialize(ProductReview $r): array
    {
        return [
            'id'          => $r->getId(),
            'productId'   => $r->getProduct()->getId(),
            'productName' => $r->getProduct()->getName(),
            'productSlug' => $r->getProduct()->getSlug(),
            'rating'      => $r->getRating(),
            'comment'     => $r->getComment(),
            'isApproved'  => $r->isApproved(),
            'createdAt'   => $r->getCreatedAt()->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'api_account_reviews_list', methods: ['GET'])]
    public function list(ProductReviewRepository $repo): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->json(array_map($this->serialize(...), $repo->findByUser($user)));
    }

    #[Route('', name: 'api_account_reviews_create', methods: ['POST'])]
    public function create(
        Request $request,
        ProductRepository $productRepo,
        ProductReviewRepository $reviewRepo,
        OrderRepository $orderRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $data = $request->toArray();

        $productId = (int) ($data['productId'] ?? 0);
        $rating    = (int) ($data['rating'] ?? 0);
        $comment   = trim((string) ($data['comment'] ?? ''));

        if (!$productId || $rating < 1 || $rating > 5) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $product = $productRepo->find($productId);
        if (!$product) {
            return $this->json(['message' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (!$orderRepo->hasCustomerEmailOrderedProduct($user->getEmail(), $productId)) {
            return $this->json(['message' => 'Vous devez avoir acheté ce produit pour laisser un avis.'], Response::HTTP_FORBIDDEN);
        }

        if ($reviewRepo->hasUserReviewedProduct($user, $product)) {
            return $this->json(['message' => 'Vous avez déjà laissé un avis sur ce produit.'], Response::HTTP_CONFLICT);
        }

        $review = new ProductReview();
        $review->setProduct($product);
        $review->setUser($user);
        $review->setAuthorName($user->getEmail());
        $review->setRating($rating);
        $review->setComment('' !== $comment ? $comment : null);

        $em->persist($review);
        $em->flush();

        return $this->json($this->serialize($review), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_account_reviews_delete', methods: ['DELETE'])]
    public function delete(int $id, ProductReviewRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $review = $repo->find($id);

        if (!$review || $review->getUser()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Avis introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($review->isApproved()) {
            return $this->json(['message' => 'Un avis publié ne peut plus être supprimé.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($review);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

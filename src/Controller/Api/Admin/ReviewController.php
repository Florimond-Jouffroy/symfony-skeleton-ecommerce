<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\ProductReview;
use App\Repository\ProductReviewRepository;
use App\Security\Voter\ReviewVoter;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/avis')]
class ReviewController extends AbstractController
{
    private function serialize(ProductReview $r): array
    {
        return [
            'id'          => $r->getId(),
            'productId'   => $r->getProduct()->getId(),
            'productName' => $r->getProduct()->getName(),
            'productSlug' => $r->getProduct()->getSlug(),
            'authorName'  => $r->getAuthorName(),
            'rating'      => $r->getRating(),
            'comment'     => $r->getComment(),
            'isApproved'  => $r->isApproved(),
            'createdAt'   => $r->getCreatedAt()->format('Y-m-d H:i'),
        ];
    }

    #[Route('', name: 'api_admin_reviews_list', methods: ['GET'])]
    public function list(Request $request, ProductReviewRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted(ReviewVoter::VIEW);

        $filter = $request->query->get('approved');
        $approved = match ($filter) {
            'true'  => true,
            'false' => false,
            default => null,
        };

        return $this->json(array_map($this->serialize(...), $repo->findForAdmin($approved)));
    }

    #[Route('/{id}', name: 'api_admin_reviews_update', methods: ['PATCH'])]
    public function update(int $id, Request $request, ProductReviewRepository $repo, EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
    {
        $this->denyAccessUnlessGranted(ReviewVoter::EDIT);

        $review = $repo->find($id);
        if (!$review) {
            return $this->json(['message' => 'Avis introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();
        if (array_key_exists('isApproved', $data)) {
            $review->setIsApproved((bool) $data['isApproved']);
        }

        $em->flush();

        $action = $review->isApproved() ? 'review.approved' : 'review.unapproved';
        $activityLogger->log(
            $action,
            'review',
            $review->getId(),
            $review->getProduct()->getName(),
            ['author' => $review->getAuthorName(), 'rating' => $review->getRating()],
        );

        return $this->json($this->serialize($review));
    }

    #[Route('/{id}', name: 'api_admin_reviews_delete', methods: ['DELETE'])]
    public function delete(int $id, ProductReviewRepository $repo, EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
    {
        $this->denyAccessUnlessGranted(ReviewVoter::DELETE);

        $review = $repo->find($id);
        if (!$review) {
            return $this->json(['message' => 'Avis introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $reviewId    = $review->getId();
        $productName = $review->getProduct()->getName();
        $author      = $review->getAuthorName();
        $rating      = $review->getRating();

        $em->remove($review);
        $em->flush();

        $activityLogger->log(
            'review.deleted',
            'review',
            $reviewId,
            $productName,
            ['author' => $author, 'rating' => $rating],
        );

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

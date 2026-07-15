<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Account;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductReview;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReviewTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testSubmitRequiresAuthentication(): void
    {
        $this->postJson('/api/compte/avis', ['productId' => 1, 'rating' => 5]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/compte/avis');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function testListReturnsUserReviews(): void
    {
        $user    = $this->createUser('client@example.com');
        $other   = $this->createUser('other@example.com');
        $product = $this->createProduct('Produit', 'produit', Product::STATUS_PUBLISHED);
        $this->createProductReview($product, $user, 5, true);
        $this->createProductReview($product, $other, 4, true);

        $this->loginAs($user);
        $this->client->request('GET', '/api/compte/avis');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(1, $data);
        self::assertSame(5, $data[0]['rating']);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function testSubmitReviewAfterPurchase(): void
    {
        $user     = $this->createUser('client@example.com');
        $customer = $this->createCustomer('client@example.com');
        $product  = $this->createProduct('Produit', 'produit', Product::STATUS_PUBLISHED);
        $this->createOrderWithProduct($customer, $product, 'ORD-001', Order::STATUS_DELIVERED);

        $this->loginAs($user);
        $this->postJson('/api/compte/avis', [
            'productId' => $product->getId(),
            'rating'    => 4,
            'comment'   => 'Très bien !',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame(4, $data['rating']);
        self::assertSame('Très bien !', $data['comment']);
        self::assertFalse($data['isApproved']);
    }

    public function testSubmitReviewWithoutPurchaseReturns403(): void
    {
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit', 'produit', Product::STATUS_PUBLISHED);

        $this->loginAs($user);
        $this->postJson('/api/compte/avis', [
            'productId' => $product->getId(),
            'rating'    => 5,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSubmitDuplicateReviewReturns409(): void
    {
        $user     = $this->createUser('client@example.com');
        $customer = $this->createCustomer('client@example.com');
        $product  = $this->createProduct('Produit', 'produit', Product::STATUS_PUBLISHED);
        $this->createOrderWithProduct($customer, $product, 'ORD-001', Order::STATUS_DELIVERED);
        $this->createProductReview($product, $user, 5);

        $this->loginAs($user);
        $this->postJson('/api/compte/avis', [
            'productId' => $product->getId(),
            'rating'    => 4,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testSubmitWithInvalidRatingReturns422(): void
    {
        $user = $this->createUser('client@example.com');

        $this->loginAs($user);
        $this->postJson('/api/compte/avis', [
            'productId' => 1,
            'rating'    => 6,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeletePendingReview(): void
    {
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit', 'produit', Product::STATUS_PUBLISHED);
        $review  = $this->createProductReview($product, $user, 5, false);
        $id      = $review->getId();

        $this->loginAs($user);
        $this->client->request('DELETE', '/api/compte/avis/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(ProductReview::class)->find($id));
    }

    public function testCannotDeleteApprovedReview(): void
    {
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit', 'produit', Product::STATUS_PUBLISHED);
        $review  = $this->createProductReview($product, $user, 5, true);

        $this->loginAs($user);
        $this->client->request('DELETE', '/api/compte/avis/'.$review->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotDeleteAnotherUsersReview(): void
    {
        $userA   = $this->createUser('a@example.com');
        $userB   = $this->createUser('b@example.com');
        $product = $this->createProduct('Produit', 'produit', Product::STATUS_PUBLISHED);
        $review  = $this->createProductReview($product, $userA, 5);

        $this->loginAs($userB);
        $this->client->request('DELETE', '/api/compte/avis/'.$review->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}

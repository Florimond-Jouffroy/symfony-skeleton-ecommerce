<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\Product;
use App\Entity\ProductReview;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReviewTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/avis');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/avis');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function testListReturnsAllReviews(): void
    {
        $this->loginAs($this->createAdmin());
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit A', 'produit-a', Product::STATUS_PUBLISHED);
        $this->createProductReview($product, $user, 5, false);
        $this->createProductReview($product, $this->createUser('b@example.com'), 3, true);

        $this->client->request('GET', '/api/admin/avis');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(2, $data);
        self::assertArrayHasKey('id', $data[0]);
        self::assertArrayHasKey('productName', $data[0]);
        self::assertArrayHasKey('authorName', $data[0]);
        self::assertArrayHasKey('rating', $data[0]);
        self::assertArrayHasKey('isApproved', $data[0]);
    }

    public function testListFiltersPendingReviews(): void
    {
        $this->loginAs($this->createAdmin());
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit A', 'produit-a', Product::STATUS_PUBLISHED);
        $this->createProductReview($product, $user, 5, false);
        $this->createProductReview($product, $this->createUser('b@example.com'), 4, true);

        $this->client->request('GET', '/api/admin/avis', ['approved' => 'false']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(1, $data);
        self::assertFalse($data[0]['isApproved']);
    }

    public function testListFiltersApprovedReviews(): void
    {
        $this->loginAs($this->createAdmin());
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit A', 'produit-a', Product::STATUS_PUBLISHED);
        $this->createProductReview($product, $user, 5, false);
        $this->createProductReview($product, $this->createUser('b@example.com'), 4, true);

        $this->client->request('GET', '/api/admin/avis', ['approved' => 'true']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(1, $data);
        self::assertTrue($data[0]['isApproved']);
    }

    // ── Approbation ───────────────────────────────────────────────────────────

    public function testApproveReview(): void
    {
        $this->loginAs($this->createAdmin());
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit A', 'produit-a', Product::STATUS_PUBLISHED);
        $review  = $this->createProductReview($product, $user, 5, false);

        $this->patchJson('/api/admin/avis/'.$review->getId(), ['isApproved' => true]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertTrue($data['isApproved']);
    }

    public function testUnapproveReview(): void
    {
        $this->loginAs($this->createAdmin());
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit A', 'produit-a', Product::STATUS_PUBLISHED);
        $review  = $this->createProductReview($product, $user, 5, true);

        $this->patchJson('/api/admin/avis/'.$review->getId(), ['isApproved' => false]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertFalse($data['isApproved']);
    }

    public function testApproveNonExistentReviewReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->patchJson('/api/admin/avis/99999', ['isApproved' => true]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeleteReview(): void
    {
        $this->loginAs($this->createAdmin());
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit A', 'produit-a', Product::STATUS_PUBLISHED);
        $review  = $this->createProductReview($product, $user);
        $id      = $review->getId();

        $this->client->request('DELETE', '/api/admin/avis/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(ProductReview::class)->find($id));
    }

    public function testDeleteNonExistentReviewReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('DELETE', '/api/admin/avis/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}

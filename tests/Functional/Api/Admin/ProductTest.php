<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\Product;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/produits');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser('user@example.com'));
        $this->client->request('GET', '/api/admin/produits');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser('user@example.com'));
        $this->postJson('/api/admin/produits', ['name' => 'Test']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteForbiddenForNonAdmin(): void
    {
        $product = $this->createProduct();
        $this->loginAs($this->createUser('user@example.com'));
        $this->client->request('DELETE', '/api/admin/produits/'.$product->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function testListReturnsProducts(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createProduct('T-shirt', 't-shirt');
        $this->createProduct('Pantalon', 'pantalon');

        $this->client->request('GET', '/api/admin/produits');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(2, $data['total']);
        self::assertCount(2, $data['items']);
        self::assertArrayHasKey('name', $data['items'][0]);
        self::assertArrayHasKey('price', $data['items'][0]);
        self::assertArrayHasKey('status', $data['items'][0]);
        self::assertArrayHasKey('stock', $data['items'][0]);
    }

    public function testListFiltersByStatus(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createProduct('T-shirt', 't-shirt', Product::STATUS_DRAFT);
        $this->createProduct('Pantalon', 'pantalon', Product::STATUS_PUBLISHED);

        $this->client->request('GET', '/api/admin/produits', ['status' => 'published']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(1, $data['total']);
        self::assertSame('Pantalon', $data['items'][0]['name']);
    }

    public function testListSearchByName(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createProduct('T-shirt blanc', 't-shirt-blanc');
        $this->createProduct('Pantalon noir', 'pantalon-noir');

        $this->client->request('GET', '/api/admin/produits', ['q' => 'shirt']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(1, $data['total']);
        self::assertSame('T-shirt blanc', $data['items'][0]['name']);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function testCreateProduct(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/produits', ['name' => 'Nouveau produit']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('Nouveau produit', $data['name']);
        self::assertSame(Product::STATUS_DRAFT, $data['status']);
        self::assertArrayHasKey('images', $data);
        self::assertArrayHasKey('variants', $data);
    }

    public function testCreateProductRequiresName(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/produits', ['name' => '']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function testGetProduct(): void
    {
        $this->loginAs($this->createAdmin());
        $product = $this->createProduct('T-shirt', 't-shirt');

        $this->client->request('GET', '/api/admin/produits/'.$product->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('T-shirt', $data['name']);
        self::assertArrayHasKey('images', $data);
        self::assertArrayHasKey('variants', $data);
        self::assertArrayHasKey('description', $data);
    }

    public function testGetNonExistentProductReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('GET', '/api/admin/produits/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function testUpdateProduct(): void
    {
        $this->loginAs($this->createAdmin());
        $product = $this->createProduct('T-shirt', 't-shirt');

        $this->putJson('/api/admin/produits/'.$product->getId(), [
            'name'  => 'T-shirt mis à jour',
            'price' => 2999,
            'stock' => 10,
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('T-shirt mis à jour', $data['name']);
        self::assertSame(2999, $data['price']);
        self::assertSame(10, $data['stock']);
    }

    public function testUpdateProductRequiresName(): void
    {
        $this->loginAs($this->createAdmin());
        $product = $this->createProduct('T-shirt', 't-shirt');

        $this->putJson('/api/admin/produits/'.$product->getId(), ['name' => '']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Publication ───────────────────────────────────────────────────────────

    public function testPublishDraftProduct(): void
    {
        $this->loginAs($this->createAdmin());
        $product = $this->createProduct('T-shirt', 't-shirt', Product::STATUS_DRAFT);

        $this->postJson('/api/admin/produits/'.$product->getId().'/publier', []);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(Product::STATUS_PUBLISHED, $data['status']);
    }

    public function testPublishAlreadyPublishedProductReturns422(): void
    {
        $this->loginAs($this->createAdmin());
        $product = $this->createProduct('T-shirt', 't-shirt', Product::STATUS_PUBLISHED);

        $this->postJson('/api/admin/produits/'.$product->getId().'/publier', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUnpublishPublishedProduct(): void
    {
        $this->loginAs($this->createAdmin());
        $product = $this->createProduct('T-shirt', 't-shirt', Product::STATUS_PUBLISHED);

        $this->postJson('/api/admin/produits/'.$product->getId().'/depublier', []);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(Product::STATUS_DRAFT, $data['status']);
    }

    public function testUnpublishDraftProductReturns422(): void
    {
        $this->loginAs($this->createAdmin());
        $product = $this->createProduct('T-shirt', 't-shirt', Product::STATUS_DRAFT);

        $this->postJson('/api/admin/produits/'.$product->getId().'/depublier', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeleteProduct(): void
    {
        $this->loginAs($this->createAdmin());
        $product = $this->createProduct('T-shirt', 't-shirt');
        $id      = $product->getId();

        $this->client->request('DELETE', '/api/admin/produits/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(Product::class)->find($id));
    }

    public function testDeleteNonExistentProductReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('DELETE', '/api/admin/produits/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}

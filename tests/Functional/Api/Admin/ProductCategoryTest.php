<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\ProductCategory;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductCategoryTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/categories-produits');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser('user@example.com'));
        $this->client->request('GET', '/api/admin/categories-produits');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser('user@example.com'));
        $this->postJson('/api/admin/categories-produits', ['name' => 'Test']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUpdateForbiddenForNonAdmin(): void
    {
        $category = $this->createProductCategory();
        $this->loginAs($this->createUser('user@example.com'));
        $this->putJson('/api/admin/categories-produits/'.$category->getId(), ['name' => 'Mode']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteForbiddenForNonAdmin(): void
    {
        $category = $this->createProductCategory();
        $this->loginAs($this->createUser('user@example.com'));
        $this->client->request('DELETE', '/api/admin/categories-produits/'.$category->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function testListReturnsCategories(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createProductCategory('Vêtements', 'vetements');
        $this->createProductCategory('Accessoires', 'accessoires');

        $this->client->request('GET', '/api/admin/categories-produits');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(2, $data);
        self::assertArrayHasKey('id', $data[0]);
        self::assertArrayHasKey('name', $data[0]);
        self::assertArrayHasKey('slug', $data[0]);
        self::assertArrayHasKey('productCount', $data[0]);
    }

    public function testListIsEmptyByDefault(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('GET', '/api/admin/categories-produits');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->getJson());
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function testCreateCategory(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/categories-produits', ['name' => 'Nouveautés']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('Nouveautés', $data['name']);
        self::assertNotEmpty($data['slug']);
        self::assertSame(0, $data['productCount']);
    }

    public function testCreateCategoryRequiresName(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/categories-produits', ['name' => '   ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateCategoryWithEmptyNameReturns422(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/categories-produits', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function testUpdateCategory(): void
    {
        $this->loginAs($this->createAdmin());
        $category = $this->createProductCategory('Vêtements', 'vetements');

        $this->putJson('/api/admin/categories-produits/'.$category->getId(), ['name' => 'Mode']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('Mode', $data['name']);
    }

    public function testUpdateCategoryRequiresName(): void
    {
        $this->loginAs($this->createAdmin());
        $category = $this->createProductCategory('Vêtements', 'vetements');

        $this->putJson('/api/admin/categories-produits/'.$category->getId(), ['name' => '']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateNonExistentCategoryReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->putJson('/api/admin/categories-produits/99999', ['name' => 'Test']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeleteCategory(): void
    {
        $this->loginAs($this->createAdmin());
        $category = $this->createProductCategory('À supprimer', 'a-supprimer');
        $id       = $category->getId();

        $this->client->request('DELETE', '/api/admin/categories-produits/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(ProductCategory::class)->find($id));
    }

    public function testDeleteNonExistentCategoryReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('DELETE', '/api/admin/categories-produits/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}

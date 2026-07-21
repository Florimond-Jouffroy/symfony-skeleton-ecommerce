<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\Category;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class CategoryTest extends AbstractApiTestCase
{
    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/categories');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateCategory(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/categories', ['name' => 'Actualités']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('Actualités', $data['name']);
        self::assertNotEmpty($data['slug']);
    }

    public function testCreateRejectsBlankName(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/categories', ['name' => '   ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeleteCategory(): void
    {
        $this->loginAs($this->createAdmin());
        $category = new Category();
        $category->setName('À supprimer');
        $this->em->persist($category);
        $this->em->flush();
        $id = $category->getId();

        $this->client->request('DELETE', '/api/admin/categories/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(Category::class)->find($id));
    }
}

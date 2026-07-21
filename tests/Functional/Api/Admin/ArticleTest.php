<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ArticleTest extends AbstractApiTestCase
{
    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/articles');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateArticle(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/articles', [
            'title'   => 'Mon premier article',
            'content' => [['type' => 'paragraph', 'text' => 'Bonjour']],
            'excerpt' => 'Un résumé',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('Mon premier article', $data['title']);
        self::assertSame('Un résumé', $data['excerpt']);
        self::assertNotEmpty($data['slug']);
    }

    public function testCreateRejectsBlankTitle(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/articles', ['title' => '   ', 'content' => []]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateNormalizesEmptyExcerptToNull(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/articles', [
            'title'      => 'Sans résumé',
            'content'    => [],
            'excerpt'    => '',
            'coverImage' => '',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertNull($data['excerpt']);
        self::assertNull($data['coverImage']);
    }

    public function testUpdateArticle(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/articles', ['title' => 'Titre initial', 'content' => []]);
        $id = $this->getJson()['id'];

        $this->putJson('/api/admin/articles/'.$id, [
            'title'   => 'Titre modifié',
            'content' => [['type' => 'paragraph', 'text' => 'Nouveau']],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('Titre modifié', $this->getJson()['title']);
    }
}

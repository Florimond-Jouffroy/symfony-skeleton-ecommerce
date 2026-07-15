<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\StaticPage;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class StaticPageTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/pages');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/pages');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function testListReturnsPagesOrderedByTitle(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createStaticPage('Mentions légales', 'mentions-legales');
        $this->createStaticPage('CGV', 'cgv');

        $this->client->request('GET', '/api/admin/pages');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(2, $data);
        self::assertSame('CGV', $data[0]['title']);
        self::assertSame('Mentions légales', $data[1]['title']);
        self::assertArrayHasKey('slug', $data[0]);
        self::assertArrayHasKey('isActive', $data[0]);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function testGetPageIncludesContent(): void
    {
        $this->loginAs($this->createAdmin());
        $page = $this->createStaticPage('CGV', 'cgv', true, ['blocks' => []]);

        $this->client->request('GET', '/api/admin/pages/'.$page->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('CGV', $data['title']);
        self::assertArrayHasKey('content', $data);
    }

    public function testGetNonExistentPageReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('GET', '/api/admin/pages/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function testCreatePage(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/pages', [
            'title'   => 'Politique de confidentialité',
            'slug'    => 'politique-de-confidentialite',
            'content' => ['blocks' => []],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('Politique de confidentialité', $data['title']);
        self::assertSame('politique-de-confidentialite', $data['slug']);
        self::assertTrue($data['isActive']);
    }

    public function testCreateGeneratesSlugFromTitle(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/pages', [
            'title'   => 'Mentions légales',
            'content' => [],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertNotEmpty($data['slug']);
        self::assertStringNotContainsString(' ', $data['slug']);
    }

    public function testCreateRequiresTitle(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/pages', ['slug' => 'no-title', 'content' => []]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateWithDuplicateSlugReturns422(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createStaticPage('Existante', 'mon-slug');

        $this->postJson('/api/admin/pages', [
            'title'   => 'Nouvelle',
            'slug'    => 'mon-slug',
            'content' => [],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function testUpdatePage(): void
    {
        $this->loginAs($this->createAdmin());
        $page = $this->createStaticPage('Ancienne', 'ancienne');

        $this->patchJson('/api/admin/pages/'.$page->getId(), [
            'title'    => 'Nouvelle',
            'isActive' => false,
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('Nouvelle', $data['title']);
        self::assertFalse($data['isActive']);
    }

    public function testUpdateSlugUniquenessCheck(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createStaticPage('Page A', 'slug-a');
        $pageB = $this->createStaticPage('Page B', 'slug-b');

        $this->patchJson('/api/admin/pages/'.$pageB->getId(), ['slug' => 'slug-a']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeletePage(): void
    {
        $this->loginAs($this->createAdmin());
        $page = $this->createStaticPage();
        $id   = $page->getId();

        $this->client->request('DELETE', '/api/admin/pages/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(StaticPage::class)->find($id));
    }

    public function testDeleteNonExistentPageReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('DELETE', '/api/admin/pages/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Page publique ─────────────────────────────────────────────────────────

    public function testPublicPageReturns404WhenInactive(): void
    {
        $this->createStaticPage('Brouillon', 'brouillon', false);

        $this->client->request('GET', '/pages/brouillon');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testPublicPageReturns404WhenNotFound(): void
    {
        $this->client->request('GET', '/pages/inexistante');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testPublicPageRendersActiveContent(): void
    {
        $this->createStaticPage('Mentions légales', 'mentions-legales', true);

        $this->client->request('GET', '/pages/mentions-legales');

        self::assertResponseIsSuccessful();
    }
}

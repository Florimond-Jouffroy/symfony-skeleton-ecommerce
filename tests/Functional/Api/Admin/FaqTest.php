<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\FaqItem;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class FaqTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/faq');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/faq');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function testListReturnsItemsOrderedByPosition(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createFaqItem('Question A', 'Réponse A', 2);
        $this->createFaqItem('Question B', 'Réponse B', 1);

        $this->client->request('GET', '/api/admin/faq');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(2, $data);
        self::assertSame('Question B', $data[0]['question']);
        self::assertSame('Question A', $data[1]['question']);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function testCreateFaqItem(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/faq', [
            'question' => 'Comment suivre ma commande ?',
            'answer'   => 'Depuis votre espace client.',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('Comment suivre ma commande ?', $data['question']);
        self::assertSame('Depuis votre espace client.', $data['answer']);
        self::assertTrue($data['isActive']);
        self::assertArrayHasKey('position', $data);
    }

    public function testCreateRequiresQuestion(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/faq', ['answer' => 'Réponse sans question.']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRequiresAnswer(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/faq', ['question' => 'Question sans réponse ?']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function testUpdateFaqItem(): void
    {
        $this->loginAs($this->createAdmin());
        $item = $this->createFaqItem();

        $this->patchJson('/api/admin/faq/'.$item->getId(), [
            'question' => 'Question modifiée ?',
            'isActive' => false,
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('Question modifiée ?', $data['question']);
        self::assertFalse($data['isActive']);
    }

    public function testUpdateNonExistentItemReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->patchJson('/api/admin/faq/99999', ['question' => 'X ?']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeleteFaqItem(): void
    {
        $this->loginAs($this->createAdmin());
        $item = $this->createFaqItem();
        $id   = $item->getId();

        $this->client->request('DELETE', '/api/admin/faq/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(FaqItem::class)->find($id));
    }

    public function testDeleteNonExistentItemReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('DELETE', '/api/admin/faq/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Réorganisation ────────────────────────────────────────────────────────

    public function testMoveDown(): void
    {
        $this->loginAs($this->createAdmin());
        $a = $this->createFaqItem('A', 'Ra', 1);
        $b = $this->createFaqItem('B', 'Rb', 2);

        $this->postJson('/api/admin/faq/'.$a->getId().'/move-down', []);

        self::assertResponseIsSuccessful();
        $this->em->refresh($a);
        $this->em->refresh($b);
        self::assertSame(2, $a->getPosition());
        self::assertSame(1, $b->getPosition());
    }

    public function testMoveUp(): void
    {
        $this->loginAs($this->createAdmin());
        $a = $this->createFaqItem('A', 'Ra', 1);
        $b = $this->createFaqItem('B', 'Rb', 2);

        $this->postJson('/api/admin/faq/'.$b->getId().'/move-up', []);

        self::assertResponseIsSuccessful();
        $this->em->refresh($a);
        $this->em->refresh($b);
        self::assertSame(2, $a->getPosition());
        self::assertSame(1, $b->getPosition());
    }

    // ── API publique ──────────────────────────────────────────────────────────

    public function testPublicEndpointReturnsOnlyActiveItems(): void
    {
        $this->createFaqItem('Visible', 'Oui', 1, true);
        $this->createFaqItem('Masquée', 'Non', 2, false);

        $this->client->request('GET', '/api/faq');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(1, $data);
        self::assertSame('Visible', $data[0]['question']);
    }
}

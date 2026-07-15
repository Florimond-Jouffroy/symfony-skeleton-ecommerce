<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\SupportTicket;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class SupportTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/support');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/support');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function testListReturnsTickets(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('client@example.com');
        $this->createSupportTicket('Problème livraison', $user);
        $this->createSupportTicket(
            'Question produit',
            guestName: 'Jean Dupont',
            guestEmail: 'jean@example.com',
        );

        $this->client->request('GET', '/api/admin/support');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(2, $data);
        self::assertArrayHasKey('id', $data[0]);
        self::assertArrayHasKey('subject', $data[0]);
        self::assertArrayHasKey('status', $data[0]);
        self::assertArrayHasKey('isGuest', $data[0]);
    }

    public function testListFiltersByStatus(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('client@example.com');
        $this->createSupportTicket('Open', $user, SupportTicket::STATUS_OPEN);
        $this->createSupportTicket('Closed', $user, SupportTicket::STATUS_CLOSED);

        $this->client->request('GET', '/api/admin/support', ['status' => 'open']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(1, $data);
        self::assertSame('open', $data[0]['status']);
    }

    public function testListSearchBySubject(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('client@example.com');
        $this->createSupportTicket('Problème de paiement', $user);
        $this->createSupportTicket('Question sur la livraison', $user);

        $this->client->request('GET', '/api/admin/support', ['search' => 'paiement']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(1, $data);
        self::assertSame('Problème de paiement', $data[0]['subject']);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function testGetTicketDetail(): void
    {
        $this->loginAs($this->createAdmin());
        $user   = $this->createUser('client@example.com');
        $ticket = $this->createSupportTicket('Problème', $user);

        $this->client->request('GET', '/api/admin/support/'.$ticket->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('Problème', $data['subject']);
        self::assertArrayHasKey('messages', $data);
        self::assertArrayHasKey('isGuest', $data);
        self::assertFalse($data['isGuest']);
    }

    public function testGetNonExistentTicketReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('GET', '/api/admin/support/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Mise à jour du statut ─────────────────────────────────────────────────

    public function testUpdateStatus(): void
    {
        $this->loginAs($this->createAdmin());
        $user   = $this->createUser('client@example.com');
        $ticket = $this->createSupportTicket('Ticket', $user, SupportTicket::STATUS_OPEN);

        $this->patchJson('/api/admin/support/'.$ticket->getId().'/statut', [
            'status' => SupportTicket::STATUS_IN_PROGRESS,
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(SupportTicket::STATUS_IN_PROGRESS, $data['status']);
    }

    public function testUpdateStatusWithInvalidValueReturns422(): void
    {
        $this->loginAs($this->createAdmin());
        $user   = $this->createUser('client@example.com');
        $ticket = $this->createSupportTicket('Ticket', $user);

        $this->patchJson('/api/admin/support/'.$ticket->getId().'/statut', [
            'status' => 'statut-inexistant',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Réponse admin ─────────────────────────────────────────────────────────

    public function testReplyAddsMessageToTicket(): void
    {
        $this->loginAs($this->createAdmin());
        $user   = $this->createUser('client@example.com');
        $ticket = $this->createSupportTicket('Ticket', $user);

        $this->postJson('/api/admin/support/'.$ticket->getId().'/repondre', [
            'body' => 'Bonjour, voici notre réponse.',
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertCount(1, $data['messages']);
        self::assertTrue($data['messages'][0]['isFromAdmin']);
        self::assertSame('Bonjour, voici notre réponse.', $data['messages'][0]['body']);
    }

    public function testReplyRequiresBody(): void
    {
        $this->loginAs($this->createAdmin());
        $user   = $this->createUser('client@example.com');
        $ticket = $this->createSupportTicket('Ticket', $user);

        $this->postJson('/api/admin/support/'.$ticket->getId().'/repondre', ['body' => '']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

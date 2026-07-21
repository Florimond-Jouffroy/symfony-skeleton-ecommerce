<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Account;

use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class SupportTest extends AbstractApiTestCase
{
    public function testCreateRequiresAuthentication(): void
    {
        $this->postJson('/api/account/support', ['subject' => 'X', 'body' => 'Y']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateTicket(): void
    {
        $this->loginAs($this->createUser());

        $this->postJson('/api/account/support', [
            'subject' => 'Problème de livraison',
            'body'    => 'Ma commande est en retard.',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('Problème de livraison', $data['subject']);
        self::assertCount(1, $data['messages']);
    }

    public function testCreateRejectsMissingBody(): void
    {
        $this->loginAs($this->createUser());

        $this->postJson('/api/account/support', ['subject' => 'Sujet seul']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

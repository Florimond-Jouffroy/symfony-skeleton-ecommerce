<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ContactTest extends AbstractApiTestCase
{
    public function testCreateTicket(): void
    {
        $this->postJson('/api/contact/ticket', [
            'name'    => 'Alice Martin',
            'email'   => 'alice@example.com',
            'subject' => 'Question produit',
            'body'    => 'Bonjour, avez-vous ce modèle en bleu ?',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertArrayHasKey('token', $this->getJson());
    }

    public function testCreateRejectsMissingName(): void
    {
        $this->postJson('/api/contact/ticket', [
            'email'   => 'alice@example.com',
            'subject' => 'Sujet',
            'body'    => 'Message',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsInvalidEmail(): void
    {
        $this->postJson('/api/contact/ticket', [
            'name'    => 'Alice',
            'email'   => 'pas-un-email',
            'subject' => 'Sujet',
            'body'    => 'Message',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsBlankBody(): void
    {
        $this->postJson('/api/contact/ticket', [
            'name'    => 'Alice',
            'email'   => 'alice@example.com',
            'subject' => 'Sujet',
            'body'    => '   ',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

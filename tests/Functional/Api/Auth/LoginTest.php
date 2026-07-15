<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Auth;

use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class LoginTest extends AbstractApiTestCase
{
    private const URL = '/api/auth/connexion';

    public function testSuccessfulLogin(): void
    {
        $this->createUser('valide@example.com', 'password123', verified: true);

        $this->postJson(self::URL, [
            'email' => 'valide@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseIsSuccessful();

        $body = $this->getJson();
        self::assertArrayHasKey('id', $body);
        self::assertSame('valide@example.com', $body['email']);
        self::assertContains('ROLE_USER', $body['roles']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->createUser('valide@example.com', 'password123', verified: true);

        $this->postJson(self::URL, [
            'email' => 'valide@example.com',
            'password' => 'mauvais_mot_de_passe',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('Identifiants incorrects.', $this->getJson()['message']);
    }

    public function testLoginFailsWithUnknownEmail(): void
    {
        $this->postJson(self::URL, [
            'email' => 'inconnu@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('Identifiants incorrects.', $this->getJson()['message']);
    }

    public function testLoginBlockedIfEmailNotVerified(): void
    {
        $this->createUser('nonverifie@example.com', 'password123', verified: false);

        $this->postJson(self::URL, [
            'email' => 'nonverifie@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $body = $this->getJson();
        self::assertSame('email_not_verified', $body['code']);
    }

    public function testLoginFailsWithInvalidEmail(): void
    {
        $this->postJson(self::URL, [
            'email' => 'pas-un-email',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testLoginFailsWithMissingFields(): void
    {
        $this->postJson(self::URL, []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

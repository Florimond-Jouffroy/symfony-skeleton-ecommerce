<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Auth;

use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationTest extends AbstractApiTestCase
{
    // ── Vérification via le lien e-mail ──────────────────────────────────────

    public function testVerifyEmailWithValidToken(): void
    {
        $user = $this->createUser('nonverifie@example.com', 'password123', verified: false);
        $token = $user->getVerificationToken();

        $this->client->request('GET', '/verification-email', ['token' => $token]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'vérifiée');

        $this->refreshUser($user);
        self::assertTrue($user->isVerified());
        self::assertNull($user->getVerificationToken());
    }

    public function testVerifyEmailWithInvalidToken(): void
    {
        $this->client->request('GET', '/verification-email', ['token' => 'token_invalide_xyz']);

        self::assertResponseIsSuccessful();
        // La page s'affiche mais sans succès
        self::assertSelectorTextContains('h1', 'invalide');
    }

    public function testVerifyEmailWithAlreadyVerifiedUser(): void
    {
        $user = $this->createUser('deja@example.com', 'password123', verified: true);

        // On force un token même si le compte est vérifié pour tester le cas
        $user->setVerificationToken('token_deja_verifie');
        $this->em->flush();

        $this->client->request('GET', '/verification-email', ['token' => 'token_deja_verifie']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'invalide');
    }

    public function testVerifyEmailWithMissingToken(): void
    {
        $this->client->request('GET', '/verification-email');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'invalide');
    }

    // ── Renvoi de l'e-mail de vérification ───────────────────────────────────

    public function testResendVerificationReturnsNeutralResponse(): void
    {
        $this->postJson('/api/auth/verification-email/renvoyer', [
            'email' => 'quelconque@example.com',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testResendVerificationForUnverifiedUser(): void
    {
        $this->createUser('nonverifie@example.com', 'password123', verified: false);

        $this->postJson('/api/auth/verification-email/renvoyer', [
            'email' => 'nonverifie@example.com',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testResendVerificationWithInvalidEmail(): void
    {
        $this->postJson('/api/auth/verification-email/renvoyer', [
            'email' => 'pas-un-email',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

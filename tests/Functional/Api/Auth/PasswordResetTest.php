<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Auth;

use App\Entity\PasswordResetToken;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetTest extends AbstractApiTestCase
{
    private const URL_REQUEST = '/api/auth/reinitialisation-mot-de-passe/demande';
    private const URL_CONFIRM = '/api/auth/reinitialisation-mot-de-passe/confirmation';

    // ── Demande de réinitialisation ───────────────────────────────────────────

    public function testRequestResetReturnsNeutralResponse(): void
    {
        $this->postJson(self::URL_REQUEST, ['email' => 'inconnu@example.com']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('code vous a été envoyé', $this->getJson()['message']);
    }

    public function testRequestResetCreatesTokenForExistingUser(): void
    {
        $user = $this->createUser('utilisateur@example.com', 'password123', verified: true);

        $this->postJson(self::URL_REQUEST, ['email' => 'utilisateur@example.com']);

        self::assertResponseIsSuccessful();

        $token = $this->em->getRepository(PasswordResetToken::class)->findOneBy(['user' => $user]);
        self::assertNotNull($token);
        self::assertFalse($token->isUsed());
        self::assertFalse($token->isExpired());
    }

    public function testRequestResetDeletesPreviousTokens(): void
    {
        $user = $this->createUser('utilisateur@example.com', 'password123', verified: true);
        $this->createPasswordResetToken($user, '000001');

        $this->postJson(self::URL_REQUEST, ['email' => 'utilisateur@example.com']);

        $tokens = $this->em->getRepository(PasswordResetToken::class)->findBy(['user' => $user]);
        self::assertCount(1, $tokens);
    }

    public function testRequestResetFailsWithInvalidEmail(): void
    {
        $this->postJson(self::URL_REQUEST, ['email' => 'pas-un-email']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Confirmation de la réinitialisation ──────────────────────────────────

    public function testConfirmResetChangesPassword(): void
    {
        $user = $this->createUser('utilisateur@example.com', 'ancien_password', verified: true);
        $this->createPasswordResetToken($user, '654321');

        $this->postJson(self::URL_CONFIRM, [
            'email' => 'utilisateur@example.com',
            'code' => '654321',
            'newPassword' => 'nouveau_password123',
            'newPasswordConfirm' => 'nouveau_password123',
        ]);

        self::assertResponseIsSuccessful();

        $this->refreshUser($user);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'nouveau_password123'));
    }

    public function testConfirmResetMarksTokenAsUsed(): void
    {
        $user = $this->createUser('utilisateur@example.com', 'password123', verified: true);
        $token = $this->createPasswordResetToken($user, '111222');

        $this->postJson(self::URL_CONFIRM, [
            'email' => 'utilisateur@example.com',
            'code' => '111222',
            'newPassword' => 'nouveau_password123',
            'newPasswordConfirm' => 'nouveau_password123',
        ]);

        $this->em->refresh($token);
        self::assertTrue($token->isUsed());
    }

    public function testConfirmResetFailsWithWrongCode(): void
    {
        $user = $this->createUser('utilisateur@example.com', 'password123', verified: true);
        $this->createPasswordResetToken($user, '999999');

        $this->postJson(self::URL_CONFIRM, [
            'email' => 'utilisateur@example.com',
            'code' => '000000',
            'newPassword' => 'nouveau_password123',
            'newPasswordConfirm' => 'nouveau_password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('Code invalide ou expiré.', $this->getJson()['message']);
    }

    public function testConfirmResetFailsWithExpiredToken(): void
    {
        $user = $this->createUser('utilisateur@example.com', 'password123', verified: true);
        $token = new PasswordResetToken($user, '777777', new \DateTimeImmutable('-1 minute'));
        $this->em->persist($token);
        $this->em->flush();

        $this->postJson(self::URL_CONFIRM, [
            'email' => 'utilisateur@example.com',
            'code' => '777777',
            'newPassword' => 'nouveau_password123',
            'newPasswordConfirm' => 'nouveau_password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testConfirmResetFailsWithUsedToken(): void
    {
        $user = $this->createUser('utilisateur@example.com', 'password123', verified: true);
        $token = $this->createPasswordResetToken($user, '333444');
        $token->markAsUsed();
        $this->em->flush();

        $this->postJson(self::URL_CONFIRM, [
            'email' => 'utilisateur@example.com',
            'code' => '333444',
            'newPassword' => 'nouveau_password123',
            'newPasswordConfirm' => 'nouveau_password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testConfirmResetFailsWithMismatchedPasswords(): void
    {
        $user = $this->createUser('utilisateur@example.com', 'password123', verified: true);
        $this->createPasswordResetToken($user, '555666');

        $this->postJson(self::URL_CONFIRM, [
            'email' => 'utilisateur@example.com',
            'code' => '555666',
            'newPassword' => 'nouveau_password123',
            'newPasswordConfirm' => 'different_password',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testConfirmResetFailsWithUnknownEmail(): void
    {
        $this->postJson(self::URL_CONFIRM, [
            'email' => 'inconnu@example.com',
            'code' => '123456',
            'newPassword' => 'nouveau_password123',
            'newPasswordConfirm' => 'nouveau_password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

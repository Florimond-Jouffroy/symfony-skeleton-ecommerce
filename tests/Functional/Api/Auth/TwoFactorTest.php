<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Auth;

use App\Entity\User;
use App\Tests\Functional\AbstractApiTestCase;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorTest extends AbstractApiTestCase
{
    private const LOGIN_URL   = '/api/auth/connexion';
    private const VERIFY_URL  = '/api/auth/2fa/verifier';
    private const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    private function createUserWithTotp(
        string $email    = 'user2fa@example.com',
        string $password = 'password123',
    ): User {
        $user = $this->createUser($email, $password, verified: true);
        $user->setTotpSecret(self::TOTP_SECRET);
        $this->em->flush();

        return $user;
    }

    private function validTotpCode(): string
    {
        return TOTP::createFromSecret(self::TOTP_SECRET, new NativeClock())->now();
    }

    public function testLoginWithTotpUserReturnsTwoFaRequired(): void
    {
        $this->createUserWithTotp();

        $this->postJson(self::LOGIN_URL, [
            'email'    => 'user2fa@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseIsSuccessful();

        $body = $this->getJson();
        self::assertTrue($body['2fa_required']);
        self::assertArrayHasKey('trusted_device_days', $body);
    }

    public function testVerifyWithValidCodeReturnsUser(): void
    {
        $this->createUserWithTotp();

        // Step 1 : login (populates session with _2fa_pending)
        $this->postJson(self::LOGIN_URL, [
            'email'    => 'user2fa@example.com',
            'password' => 'password123',
        ]);
        self::assertResponseIsSuccessful();

        // Step 2 : verify TOTP
        $this->postJson(self::VERIFY_URL, [
            'code'         => $this->validTotpCode(),
            'rememberDevice' => false,
        ]);

        self::assertResponseIsSuccessful();

        $body = $this->getJson();
        self::assertArrayHasKey('id', $body);
        self::assertSame('user2fa@example.com', $body['email']);
    }

    public function testVerifyWithInvalidCodeReturns422(): void
    {
        $this->createUserWithTotp();

        $this->postJson(self::LOGIN_URL, [
            'email'    => 'user2fa@example.com',
            'password' => 'password123',
        ]);

        $this->postJson(self::VERIFY_URL, [
            'code'          => '000000',
            'rememberDevice' => false,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('invalide', $this->getJson()['message']);
    }

    public function testVerifyWithoutPendingSessionReturns401(): void
    {
        $this->postJson(self::VERIFY_URL, [
            'code'          => '123456',
            'rememberDevice' => false,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testVerifyWithRememberDeviceSetsHttpOnlyCookie(): void
    {
        // Requires trusted_device_days > 0 (default is 30 when no DB row exists)
        $this->createUserWithTotp();

        $this->postJson(self::LOGIN_URL, [
            'email'    => 'user2fa@example.com',
            'password' => 'password123',
        ]);

        $body = $this->getJson();
        $days = $body['trusted_device_days'] ?? 0;

        if ($days <= 0) {
            $this->markTestSkipped('trusted_device_days is 0 — trusted device feature disabled in this environment.');
        }

        $this->postJson(self::VERIFY_URL, [
            'code'          => $this->validTotpCode(),
            'rememberDevice' => true,
        ]);

        self::assertResponseIsSuccessful();

        $cookieHeader = strtolower($this->client->getResponse()->headers->get('Set-Cookie', ''));
        self::assertStringContainsString('_td_2fa', $cookieHeader);
        self::assertStringContainsString('httponly', $cookieHeader);
    }

    public function testLoginWithValidTrustedDeviceCookieSkips2Fa(): void
    {
        $user = $this->createUserWithTotp();

        // Step 1 : first full login + verify + remember
        $this->postJson(self::LOGIN_URL, [
            'email'    => 'user2fa@example.com',
            'password' => 'password123',
        ]);
        $loginBody = $this->getJson();

        if (($loginBody['trusted_device_days'] ?? 0) <= 0) {
            $this->markTestSkipped('trusted_device_days is 0 — trusted device feature disabled.');
        }

        $this->postJson(self::VERIFY_URL, [
            'code'          => $this->validTotpCode(),
            'rememberDevice' => true,
        ]);
        self::assertResponseIsSuccessful();

        // Cookie is set on the client's cookie jar automatically
        // Step 2 : login again — should skip 2FA entirely
        $this->postJson(self::LOGIN_URL, [
            'email'    => 'user2fa@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseIsSuccessful();

        $body = $this->getJson();
        self::assertArrayHasKey('id', $body);
        self::assertArrayNotHasKey('2fa_required', $body);
    }
}

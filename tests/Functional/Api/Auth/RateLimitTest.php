<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Auth;

use App\Repository\AppSettingRepository;
use App\Tests\Functional\AbstractApiTestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Response;

class RateLimitTest extends AbstractApiTestCase
{
    private const LOGIN_URL    = '/api/auth/connexion';
    private const REGISTER_URL = '/api/auth/inscription';
    private const RESET_URL    = '/api/auth/reinitialisation-mot-de-passe/demande';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearRateLimitCache();
        $this->setRateLimitSettings(maxAttempts: 3, windowMinutes: 15);
    }

    protected function tearDown(): void
    {
        $this->clearRateLimitCache();
        parent::tearDown();
    }

    private function clearRateLimitCache(): void
    {
        /** @var CacheItemPoolInterface $cache */
        $cache = static::getContainer()->get('cache.app');
        $cache->clear();
    }

    private function setRateLimitSettings(int $maxAttempts, int $windowMinutes): void
    {
        /** @var AppSettingRepository $repo */
        $repo = static::getContainer()->get(AppSettingRepository::class);
        $repo->setValue('security.rate_limit.max_attempts', (string) $maxAttempts);
        $repo->setValue('security.rate_limit.window_minutes', (string) $windowMinutes);
    }

    public function testLoginIsBlockedAfterMaxAttempts(): void
    {
        $this->createUser('limite@example.com', 'password123', verified: true);

        // 3 failed attempts (bad password)
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::LOGIN_URL, [
                'email'    => 'limite@example.com',
                'password' => 'mauvais',
            ]);
        }

        // 4th attempt — should be rate-limited
        $this->postJson(self::LOGIN_URL, [
            'email'    => 'limite@example.com',
            'password' => 'mauvais',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);

        $body = $this->getJson();
        self::assertArrayHasKey('retry_after', $body);
        self::assertStringContainsString('Trop de tentatives', $body['message']);

        $retryAfter = $this->client->getResponse()->headers->get('Retry-After');
        self::assertNotNull($retryAfter);
        self::assertGreaterThan(0, (int) $retryAfter);
    }

    public function testSuccessfulLoginResetsCounter(): void
    {
        $this->createUser('reset@example.com', 'password123', verified: true);

        // 2 failed attempts
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(self::LOGIN_URL, [
                'email'    => 'reset@example.com',
                'password' => 'mauvais',
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }

        // Successful login — resets the counter
        $this->postJson(self::LOGIN_URL, [
            'email'    => 'reset@example.com',
            'password' => 'password123',
        ]);
        self::assertResponseIsSuccessful();

        // Now a bad attempt should not be blocked (counter was reset)
        $this->postJson(self::LOGIN_URL, [
            'email'    => 'reset@example.com',
            'password' => 'mauvais',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRegistrationIsRateLimited(): void
    {
        // 3 registration attempts
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::REGISTER_URL, [
                'email'           => "user{$i}@example.com",
                'password'        => 'password123',
                'passwordConfirm' => 'password123',
            ]);
        }

        // 4th attempt should be blocked
        $this->postJson(self::REGISTER_URL, [
            'email'           => 'extra@example.com',
            'password'        => 'password123',
            'passwordConfirm' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testPasswordResetIsRateLimited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::RESET_URL, ['email' => 'anyone@example.com']);
        }

        $this->postJson(self::RESET_URL, ['email' => 'anyone@example.com']);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testRateLimitingCanBeDisabledWithZeroAttempts(): void
    {
        $this->setRateLimitSettings(maxAttempts: 0, windowMinutes: 15);

        $this->createUser('nodisable@example.com', 'password123', verified: true);

        // Many failed attempts — should never be blocked
        for ($i = 0; $i < 20; $i++) {
            $this->postJson(self::LOGIN_URL, [
                'email'    => 'nodisable@example.com',
                'password' => 'mauvais',
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }
    }
}

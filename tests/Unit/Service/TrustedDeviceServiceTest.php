<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Repository\TrustedDeviceRepository;
use App\Service\TrustedDeviceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustedDeviceServiceTest extends TestCase
{
    private TrustedDeviceService $service;
    private MockObject&TrustedDeviceRepository $repo;
    private MockObject&EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(TrustedDeviceRepository::class);
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $this->service = new TrustedDeviceService($this->em, $this->repo);
    }

    private function makeUser(int $id = 1): User
    {
        $user = new User();
        // Force a known ID via reflection (entity not persisted in unit test)
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }

    public function testIsTrustedReturnsFalseWhenNoCookie(): void
    {
        $request = Request::create('/');
        $user    = $this->makeUser();

        $this->repo->expects(self::never())->method('findValidForUser');

        self::assertFalse($this->service->isTrusted($request, $user));
    }

    public function testIsTrustedReturnsFalseWhenCookieFormatInvalid(): void
    {
        $request = Request::create('/');
        $request->cookies->set(TrustedDeviceService::COOKIE_NAME, 'invalid-no-pipe');
        $user = $this->makeUser();

        $this->repo->expects(self::never())->method('findValidForUser');

        self::assertFalse($this->service->isTrusted($request, $user));
    }

    public function testIsTrustedReturnsFalseWhenUserIdMismatch(): void
    {
        $request = Request::create('/');
        $request->cookies->set(TrustedDeviceService::COOKIE_NAME, '99|somerawtoken');
        $user = $this->makeUser(1);

        $this->repo->expects(self::never())->method('findValidForUser');

        self::assertFalse($this->service->isTrusted($request, $user));
    }

    public function testIsTrustedReturnsTrueWhenValidTokenInDb(): void
    {
        $user     = $this->makeUser(1);
        $rawToken = 'validrawtoken';
        $request  = Request::create('/');
        $request->cookies->set(TrustedDeviceService::COOKIE_NAME, '1|' . $rawToken);

        $this->repo->expects(self::once())->method('deleteExpiredForUser')->with($user);
        $this->repo->expects(self::once())
            ->method('findValidForUser')
            ->with($user, hash('sha256', $rawToken))
            ->willReturn($this->createMock(TrustedDevice::class));

        self::assertTrue($this->service->isTrusted($request, $user));
    }

    public function testIsTrustedReturnsFalseWhenTokenNotInDb(): void
    {
        $user     = $this->makeUser(1);
        $rawToken = 'unknowntoken';
        $request  = Request::create('/');
        $request->cookies->set(TrustedDeviceService::COOKIE_NAME, '1|' . $rawToken);

        $this->repo->expects(self::once())->method('deleteExpiredForUser')->with($user);
        $this->repo->expects(self::once())
            ->method('findValidForUser')
            ->with($user, hash('sha256', $rawToken))
            ->willReturn(null);

        self::assertFalse($this->service->isTrusted($request, $user));
    }

    public function testTrustPersistsDeviceAndSetsCookie(): void
    {
        $user = $this->makeUser(1);

        $this->em->expects(self::once())->method('persist')->with(self::isInstanceOf(TrustedDevice::class));
        $this->em->expects(self::once())->method('flush');

        $response = new Response();
        $this->service->trust($response, $user, 30);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        $cookie = $cookies[0];
        self::assertSame(TrustedDeviceService::COOKIE_NAME, $cookie->getName());
        self::assertTrue($cookie->isHttpOnly());
        self::assertStringStartsWith('1|', $cookie->getValue());

        // Expiry should be ~30 days from now
        $expectedExpiry = new \DateTimeImmutable('+30 days');
        self::assertEqualsWithDelta($expectedExpiry->getTimestamp(), $cookie->getExpiresTime(), 5);
    }

    public function testTrustTokenIsHashedInDb(): void
    {
        $user = $this->makeUser(1);

        $persistedDevice = null;
        $this->em->expects(self::once())
            ->method('persist')
            ->willReturnCallback(function (TrustedDevice $device) use (&$persistedDevice): void {
                $persistedDevice = $device;
            });
        $this->em->expects(self::once())->method('flush');

        $response = new Response();
        $this->service->trust($response, $user, 7);

        $cookies  = $response->headers->getCookies();
        $rawToken = explode('|', $cookies[0]->getValue(), 2)[1];

        self::assertNotNull($persistedDevice);
        self::assertSame(hash('sha256', $rawToken), $persistedDevice->getTokenHash());
    }
}

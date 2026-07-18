<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Repository\TrustedDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustedDeviceService
{
    public const COOKIE_NAME = '_td_2fa';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TrustedDeviceRepository $repository,
    ) {}

    public function isTrusted(Request $request, User $user): bool
    {
        $cookieValue = $request->cookies->get(self::COOKIE_NAME);
        if (!$cookieValue) {
            return false;
        }

        $parts = explode('|', $cookieValue, 2);
        if (2 !== count($parts) || (string) $user->getId() !== $parts[0] || '' === $parts[1]) {
            return false;
        }

        $this->repository->deleteExpiredForUser($user);

        return null !== $this->repository->findValidForUser($user, hash('sha256', $parts[1]));
    }

    public function trust(Response $response, User $user, int $days): void
    {
        $rawToken  = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable("+{$days} days");

        $device = new TrustedDevice($user, hash('sha256', $rawToken), $expiresAt);
        $this->em->persist($device);
        $this->em->flush();

        $response->headers->setCookie(
            Cookie::create(self::COOKIE_NAME)
                ->withValue($user->getId().'|'.$rawToken)
                ->withExpires($expiresAt)
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_STRICT),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TrustedDevice;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TrustedDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrustedDevice::class);
    }

    public function findValidForUser(User $user, string $tokenHash): ?TrustedDevice
    {
        return $this->createQueryBuilder('td')
            ->where('td.user = :user')
            ->andWhere('td.tokenHash = :hash')
            ->andWhere('td.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteExpiredForUser(User $user): void
    {
        $this->createQueryBuilder('td')
            ->delete()
            ->where('td.user = :user')
            ->andWhere('td.expiresAt <= :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}

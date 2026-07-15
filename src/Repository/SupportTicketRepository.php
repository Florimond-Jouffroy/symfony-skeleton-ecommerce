<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupportTicket;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SupportTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportTicket::class);
    }

    /** @return SupportTicket[] */
    public function findForAdmin(?string $status = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->orderBy('t.updatedAt', 'DESC');

        if ($status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        if ($search) {
            $qb->andWhere('t.subject LIKE :s OR t.guestName LIKE :s OR t.guestEmail LIKE :s OR u.email LIKE :s')
                ->setParameter('s', "%$search%");
        }

        return $qb->getQuery()->getResult();
    }

    /** @return SupportTicket[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return SupportTicket[] */
    public function findOtherByUser(User $user, int $excludeId, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.id != :exclude')
            ->setParameter('user', $user)
            ->setParameter('exclude', $excludeId)
            ->orderBy('t.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return SupportTicket[] */
    public function findOtherByGuestEmail(string $email, int $excludeId, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.guestEmail = :email')
            ->andWhere('t.id != :exclude')
            ->setParameter('email', $email)
            ->setParameter('exclude', $excludeId)
            ->orderBy('t.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByToken(string $token): ?SupportTicket
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.status = :status')
            ->setParameter('status', SupportTicket::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

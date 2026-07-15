<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Recherche paginée sur l'email, du plus récent au plus ancien.
     *
     * @return array{items: list<User>, total: int}
     */
    public function searchPaginated(?string $query, int $page, int $pageSize): array
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.id', 'DESC');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('u.email LIKE :query')
                ->setParameter('query', '%'.addcslashes($query, '%_').'%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<User> $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}

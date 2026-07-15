<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StaticPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StaticPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StaticPage::class);
    }

    public function findBySlug(string $slug): ?StaticPage
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /** @return StaticPage[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

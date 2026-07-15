<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes Doctrine pour l'entité Product.
 *
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return array{items: list<Product>, total: int}
     */
    public function searchPaginated(
        ?string $query,
        int $page,
        int $pageSize,
        ?string $status = null,
        ?int $categoryId = null,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('p.name LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (null !== $status) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        if (null !== $categoryId) {
            $qb->join('p.categories', 'c')
                ->andWhere('c.id = :catId')
                ->setParameter('catId', $categoryId);
        }

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        /** @var list<Product> $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Retourne les produits simples (sans variantes) dont le stock est en dessous
     * du seuil d'alerte. Utilisé par le dashboard pour les alertes stock bas.
     * Les produits avec variantes ne sont pas inclus : leur stock se calcule
     * différemment (somme des variantes actives).
     *
     * @return list<Product>
     */
    public function findLowStock(): array
    {
        /** @var list<Product> */
        return $this->createQueryBuilder('p')
            ->where('p.hasVariants = false')
            ->andWhere('p.status = :status')
            ->andWhere('p.stock <= p.lowStockThreshold')
            ->setParameter('status', Product::STATUS_PUBLISHED)
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

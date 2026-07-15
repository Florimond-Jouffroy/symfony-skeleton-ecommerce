<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findOneByOrder(Order $order): ?Invoice
    {
        return $this->findOneBy(['order' => $order]);
    }

    public function getMaxSequenceForYear(int $year): int
    {
        $prefix = sprintf('FACT-%d-', $year);
        $result = $this->createQueryBuilder('i')
            ->select('i.invoiceNumber')
            ->where('i.invoiceNumber LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('i.invoiceNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $result) {
            return 0;
        }

        $suffix = substr($result['invoiceNumber'], strlen($prefix));
        return (int) $suffix;
    }

    /** @return Invoice[] */
    public function findAllWithOrder(): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.order', 'o')
            ->addSelect('o')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

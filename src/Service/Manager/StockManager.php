<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\ReturnRequest;
use App\Exception\InsufficientStockException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gère les mouvements de stock des produits et variantes de façon sûre en concurrence.
 *
 * La décrémentation s'effectue sous verrou pessimiste (SELECT ... FOR UPDATE) après
 * relecture de la valeur fraîche en base : deux commandes simultanées du dernier
 * article ne peuvent plus provoquer de survente.
 */
class StockManager
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Verrouille chaque produit/variante, revérifie la disponibilité sur la valeur
     * fraîche puis décrémente le stock. DOIT être appelée dans une transaction
     * (le verrou pessimiste l'exige).
     *
     * @param list<array{product: Product, variant: ProductVariant|null, qty: int, ...}> $lines
     *
     * @throws InsufficientStockException si le stock disponible est insuffisant
     */
    public function decrementForLines(array $lines): void
    {
        foreach ($lines as $line) {
            $target = $line['variant'] ?? $line['product'];

            // Verrou pessimiste + relecture de la valeur fraîche en base.
            $this->em->refresh($target, LockMode::PESSIMISTIC_WRITE);

            if ($target->getStock() < $line['qty']) {
                throw new InsufficientStockException($this->label($line));
            }

            $target->setStock($target->getStock() - $line['qty']);
        }
    }

    /**
     * Réincrémente le stock des lignes d'une commande (ex. : annulation).
     * Ignore les lignes dont le produit/variante a été supprimé depuis.
     */
    public function restoreForOrder(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $target = $item->getVariant() ?? $item->getProduct();
            if (null === $target) {
                continue;
            }

            $target->setStock($target->getStock() + $item->getQuantity());
        }
    }

    /**
     * Réincrémente le stock pour les articles d'une demande de retour, à la
     * quantité retournée par ligne (retour partiel possible).
     */
    public function restoreForReturn(ReturnRequest $return): void
    {
        foreach ($return->getItems() as $returnItem) {
            $orderItem = $returnItem->getOrderItem();
            $target    = $orderItem->getVariant() ?? $orderItem->getProduct();
            if (null === $target) {
                continue;
            }

            $target->setStock($target->getStock() + $returnItem->getQuantity());
        }
    }

    /**
     * @param array{product: Product, variant: ProductVariant|null, qty: int, ...} $line
     */
    private function label(array $line): string
    {
        if (null !== $line['variant']) {
            return sprintf('%s – %s', $line['product']->getName(), $line['variant']->getName());
        }

        return $line['product']->getName();
    }
}

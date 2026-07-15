<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderStatusHistory;
use App\Repository\OrderRepository;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;

/**
 * Gère les opérations métier sur les commandes.
 *
 * Toutes les méthodes retournent bool ou ?Order pour indiquer le succès.
 * En cas d'exception lors du flush(), l'erreur est loguée via ApplicationLogManager
 * et la méthode retourne false/null au lieu de laisser l'exception remonter.
 * Le contrôleur traduit ce retour en réponse HTTP 500.
 */
class OrderManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationLogManager $logManager,
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceService $invoiceService,
    ) {
    }

    /**
     * Crée une commande complète avec ses lignes et son premier historique.
     * Le numéro de commande (ORD-YYYYMMDD-XXXXX) est généré automatiquement.
     * Le statut initial est toujours "pending".
     *
     * @param array<array{productName: string, variantName?: string|null, unitPrice: int, quantity: int, productId?: int|null, variantId?: int|null}> $items
     * @param array<string, string> $shippingAddress
     * @param array<string, string>|null $billingAddress
     */
    public function create(
        Customer $customer,
        array $items,
        array $shippingAddress,
        ?array $billingAddress = null,
        int $shippingAmount = 0,
        int $discountAmount = 0,
        ?string $promoCode = null,
        ?string $customerNote = null,
    ): ?Order {
        $order = new Order();
        $order->setOrderNumber($this->generateOrderNumber());
        $order->setCustomer($customer);
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);
        $order->setShippingAmount($shippingAmount);
        $order->setDiscountAmount($discountAmount);
        $order->setPromoCode($promoCode);
        $order->setCustomerNote($customerNote);

        foreach ($items as $itemData) {
            $item = new OrderItem();
            $item->setOrder($order);
            $item->setProductName($itemData['productName']);
            $item->setVariantName($itemData['variantName'] ?? null);
            $item->setUnitPrice($itemData['unitPrice']);
            $item->setQuantity($itemData['quantity']);
            $item->recalculateTotal();
            $this->em->persist($item);
        }

        $order->recalculateTotal();

        $history = new OrderStatusHistory();
        $history->setOrder($order);
        $history->setStatus(Order::STATUS_PENDING);
        $history->setComment('Commande créée.');
        $this->em->persist($history);

        $this->em->persist($order);

        return $this->flush() ? $order : null;
    }

    /**
     * Applique une transition de statut sur la commande.
     * Vérifie d'abord que la transition est autorisée (canTransitionTo),
     * puis crée une entrée dans l'historique pour tracer le changement.
     * Retourne false si la transition est invalide ou si le flush échoue.
     */
    public function transition(Order $order, string $newStatus, ?string $comment = null): bool
    {
        if (!$order->canTransitionTo($newStatus)) {
            return false;
        }

        $order->setStatus($newStatus);

        $history = new OrderStatusHistory();
        $history->setOrder($order);
        $history->setStatus($newStatus);
        $history->setComment($comment);
        $this->em->persist($history);

        if (!$this->flush()) {
            return false;
        }

        // Auto-generate invoice on confirmation if trigger = on_confirm
        if (Order::STATUS_CONFIRMED === $newStatus
            && 'on_confirm' === $this->invoiceService->getInvoiceTrigger()
            && null === $this->invoiceService->findForOrder($order)
        ) {
            $this->invoiceService->generateForOrder($order);
        }

        return true;
    }

    public function updateInternalNote(Order $order, ?string $note): bool
    {
        $order->setInternalNote($note);

        return $this->flush();
    }

    public function delete(Order $order): bool
    {
        $this->em->remove($order);

        return $this->flush();
    }

    /**
     * Génère un numéro de commande lisible au format ORD-YYYYMMDD-XXXXX.
     * Exemple : ORD-20260712-00042
     * Le séquenceur est basé sur COUNT+1 (voir OrderRepository::getNextSequence).
     */
    private function generateOrderNumber(): string
    {
        $seq = $this->orderRepository->getNextSequence();

        return sprintf('ORD-%s-%05d', date('Ymd'), $seq);
    }

    private function flush(): bool
    {
        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logManager->reportException($e);

            return false;
        }

        return true;
    }
}

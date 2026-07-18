<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Shop;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ShippingMethod;
use App\Service\Manager\OrderManager;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class CheckoutTest extends AbstractApiTestCase
{
    private const ADDRESS = [
        'firstName'  => 'Jean',
        'lastName'   => 'Dupont',
        'line1'      => '1 rue de la Paix',
        'city'       => 'Paris',
        'postalCode' => '75001',
    ];

    public function testCheckoutDecrementsStock(): void
    {
        // Deux requêtes (panier puis commande) : on garde le même kernel/EM sur tout
        // le test pour que les entités restent gérées après la 1re requête.
        $this->client->disableReboot();
        $this->loginAs($this->createUser());
        $productId  = $this->publishedProductWithStock(5)->getId();
        $shippingId = $this->createShippingMethod()->getId();

        $this->postJson('/api/boutique/panier', ['productId' => $productId, 'quantity' => 2]);
        self::assertResponseIsSuccessful();

        $this->postJson('/api/boutique/commande', [
            'shippingMethodId' => $shippingId,
            'shippingAddress'  => self::ADDRESS,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::assertSame(3, $this->freshStock($productId));
    }

    public function testCheckoutRejectsInsufficientStock(): void
    {
        $this->client->disableReboot();
        $this->loginAs($this->createUser());
        $productId  = $this->publishedProductWithStock(5)->getId();
        $shippingId = $this->createShippingMethod()->getId();

        $this->postJson('/api/boutique/panier', ['productId' => $productId, 'quantity' => 2]);
        self::assertResponseIsSuccessful();

        // Simule une commande concurrente qui a consommé le stock entre l'ajout au
        // panier et le paiement : il ne reste qu'1 unité pour une demande de 2.
        // UPDATE direct pour contourner l'identity map (valeur réellement en base).
        $this->em->createQuery('UPDATE '.Product::class.' p SET p.stock = 1 WHERE p.id = :id')
            ->setParameter('id', $productId)
            ->execute();

        $this->postJson('/api/boutique/commande', [
            'shippingMethodId' => $shippingId,
            'shippingAddress'  => self::ADDRESS,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('Stock insuffisant', $this->getJson()['message'] ?? '');

        // Rollback : stock inchangé et aucune commande créée.
        self::assertSame(1, $this->freshStock($productId));
        self::assertCount(0, $this->em->getRepository(Order::class)->findAll());
    }

    public function testCancelledOrderRestoresStock(): void
    {
        $customer = $this->createCustomer();
        // Stock déjà décrémenté (5 → 3) par une commande de 2 unités.
        $product   = $this->publishedProductWithStock(3);
        $productId = $product->getId();
        $order     = $this->createOrder($customer, status: Order::STATUS_PENDING);

        $item = new OrderItem();
        $item
            ->setOrder($order)
            ->setProduct($product)
            ->setProductName($product->getName())
            ->setUnitPrice($product->getPrice())
            ->setQuantity(2);
        $item->recalculateTotal();
        $this->em->persist($item);
        $this->em->flush();
        // Recharge l'order pour que sa collection d'items soit peuplée depuis la base.
        $this->em->refresh($order);

        $orderManager = static::getContainer()->get(OrderManager::class);
        self::assertTrue($orderManager->transition($order, Order::STATUS_CANCELLED));

        self::assertSame(5, $this->freshStock($productId));
    }

    private function freshStock(int $productId): int
    {
        $this->em->clear();

        return $this->em->getRepository(Product::class)->find($productId)->getStock();
    }

    private function publishedProductWithStock(int $stock): Product
    {
        $product = $this->createProduct(status: Product::STATUS_PUBLISHED);
        $product->setStock($stock);
        $this->em->flush();

        return $product;
    }

    private function createShippingMethod(int $price = 500): ShippingMethod
    {
        $method = new ShippingMethod();
        $method->setName('Colissimo');
        $method->setPrice($price);
        $method->setIsActive(true);
        $method->setPosition(0);
        $this->em->persist($method);
        $this->em->flush();

        return $method;
    }
}

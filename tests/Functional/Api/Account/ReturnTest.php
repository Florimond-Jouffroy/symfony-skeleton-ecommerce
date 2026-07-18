<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Account;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ReturnItem;
use App\Entity\ReturnRequest;
use App\Repository\AppSettingRepository;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReturnTest extends AbstractApiTestCase
{
    public function testCreateForbiddenWhenReturnsDisabled(): void
    {
        $this->loginAs($this->createUser('rma@example.com'));
        $customer = $this->createCustomer('rma@example.com');
        $this->createOrderWithProduct($customer, $this->createProduct(), 'ORD-RMA-1', Order::STATUS_DELIVERED);

        $this->postJson('/api/compte/retours', ['orderNumber' => 'ORD-RMA-1', 'reason' => 'x', 'items' => []]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateRejectedForNonDeliveredOrder(): void
    {
        $this->enableReturns();
        $this->loginAs($this->createUser('rma@example.com'));
        $customer = $this->createCustomer('rma@example.com');
        $order    = $this->createOrderWithProduct($customer, $this->createProduct(), 'ORD-RMA-2', Order::STATUS_PENDING);

        $this->postJson('/api/compte/retours', [
            'orderNumber' => 'ORD-RMA-2',
            'reason'      => 'Défectueux',
            'items'       => [['orderItemId' => $this->firstItemId($order), 'quantity' => 1]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateSucceeds(): void
    {
        $this->enableReturns();
        $this->loginAs($this->createUser('rma@example.com'));
        $customer = $this->createCustomer('rma@example.com');
        $order    = $this->createOrderWithProduct($customer, $this->createProduct(), 'ORD-RMA-3', Order::STATUS_DELIVERED);

        $this->postJson('/api/compte/retours', [
            'orderNumber' => 'ORD-RMA-3',
            'reason'      => 'Défectueux',
            'items'       => [['orderItemId' => $this->firstItemId($order), 'quantity' => 1]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('requested', $this->getJson()['status']);
        self::assertCount(1, $this->em->getRepository(ReturnRequest::class)->findAll());
    }

    public function testCannotReturnMoreThanRemaining(): void
    {
        $this->enableReturns();
        $this->loginAs($this->createUser('rma@example.com'));
        $customer = $this->createCustomer('rma@example.com');

        // Commande livrée avec une ligne de quantité 2, déjà entièrement retournée.
        $product   = $this->createProduct();
        $order     = $this->createOrder($customer, 'ORD-RMA-4', Order::STATUS_DELIVERED);
        $orderItem = new OrderItem();
        $orderItem->setOrder($order)->setProduct($product)
            ->setProductName($product->getName())->setUnitPrice($product->getPrice())->setQuantity(2);
        $orderItem->recalculateTotal();
        $this->em->persist($orderItem);

        $return = new ReturnRequest();
        $return->setOrder($order)->setCustomer($customer)->setReason('Premier retour');
        $returnItem = new ReturnItem();
        $returnItem->setOrderItem($orderItem)->setQuantity(2);
        $return->addItem($returnItem);
        $this->em->persist($returnItem);
        $this->em->persist($return);
        $this->em->flush();

        // Une nouvelle demande pour ce même article doit être refusée (0 restant).
        $this->postJson('/api/compte/retours', [
            'orderNumber' => 'ORD-RMA-4',
            'reason'      => 'Second retour',
            'items'       => [['orderItemId' => $orderItem->getId(), 'quantity' => 1]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function firstItemId(Order $order): int
    {
        $this->em->refresh($order);

        return (int) $order->getItems()->first()->getId();
    }

    private function enableReturns(): void
    {
        static::getContainer()->get(AppSettingRepository::class)->setValue('returns.enabled', 'true');
    }
}

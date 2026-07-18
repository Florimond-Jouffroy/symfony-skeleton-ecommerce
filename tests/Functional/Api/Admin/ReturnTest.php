<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ReturnItem;
use App\Entity\ReturnRequest;
use App\Service\Manager\ReturnManager;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReturnTest extends AbstractApiTestCase
{
    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());

        $this->client->request('GET', '/api/admin/retours');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testApproveReturn(): void
    {
        $this->loginAs($this->createAdmin());
        $return = $this->createReturn(Order::STATUS_DELIVERED, 5, 1);

        $this->patchJson('/api/admin/retours/'.$return->getId().'/statut', ['status' => 'approved']);

        self::assertResponseIsSuccessful();
        self::assertSame(ReturnRequest::STATUS_APPROVED, $this->getJson()['status']);
    }

    public function testRefundRestocksStockAndRefundsOrder(): void
    {
        // Commande livrée de 2 unités, stock déjà décrémenté (à 3).
        $return    = $this->createReturn(Order::STATUS_DELIVERED, 3, 2);
        $productId = (int) $return->getItems()->first()->getOrderItem()->getProduct()->getId();

        $manager = static::getContainer()->get(ReturnManager::class);
        self::assertTrue($manager->transition($return, ReturnRequest::STATUS_APPROVED));
        self::assertTrue($manager->transition($return, ReturnRequest::STATUS_REFUNDED));

        self::assertSame(ReturnRequest::STATUS_REFUNDED, $return->getStatus());
        self::assertSame(Order::STATUS_REFUNDED, $return->getOrder()->getStatus());

        $this->em->clear();
        self::assertSame(5, $this->em->getRepository(Product::class)->find($productId)->getStock());
    }

    private function createReturn(string $orderStatus, int $stock, int $qty): ReturnRequest
    {
        $customer = $this->createCustomer();
        $product  = $this->createProduct();
        $product->setStock($stock);

        $order     = $this->createOrder($customer, 'ORD-RMA-'.$stock.$qty, $orderStatus);
        $orderItem = new OrderItem();
        $orderItem
            ->setOrder($order)
            ->setProduct($product)
            ->setProductName($product->getName())
            ->setUnitPrice($product->getPrice())
            ->setQuantity($qty);
        $orderItem->recalculateTotal();
        $this->em->persist($orderItem);

        $return = new ReturnRequest();
        $return->setOrder($order)->setCustomer($customer)->setReason('Défectueux');
        $returnItem = new ReturnItem();
        $returnItem->setOrderItem($orderItem)->setQuantity($qty);
        $return->addItem($returnItem);
        $this->em->persist($returnItem);
        $this->em->persist($return);
        $this->em->flush();

        return $return;
    }
}

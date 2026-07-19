<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ReturnItem;
use App\Entity\ReturnRequest;
use App\Entity\SupportTicket;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class NotificationsTest extends AbstractApiTestCase
{
    public function testRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/notifications');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/notifications');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturnsZeroCountsWhenEmpty(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('GET', '/api/admin/notifications');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(0, $data['pendingOrders']);
        self::assertSame(0, $data['openTickets']);
        self::assertSame(0, $data['pendingReviews']);
    }

    public function testCountsPendingOrders(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $this->createOrder($customer, 'ORD-001', Order::STATUS_PENDING);
        $this->createOrder($customer, 'ORD-002', Order::STATUS_PENDING);
        $this->createOrder($customer, 'ORD-003', Order::STATUS_CONFIRMED);

        $this->client->request('GET', '/api/admin/notifications');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(2, $data['pendingOrders']);
    }

    public function testCountsOpenTickets(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('client@example.com');
        $this->createSupportTicket('Ticket 1', $user, SupportTicket::STATUS_OPEN);
        $this->createSupportTicket('Ticket 2', $user, SupportTicket::STATUS_OPEN);
        $this->createSupportTicket('Ticket 3', $user, SupportTicket::STATUS_CLOSED);

        $this->client->request('GET', '/api/admin/notifications');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(2, $data['openTickets']);
    }

    public function testCountsPendingReturns(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer, 'ORD-RET-1', Order::STATUS_DELIVERED);

        $orderItem = new OrderItem();
        $orderItem->setOrder($order)->setProduct($this->createProduct())
            ->setProductName('P')->setUnitPrice(1000)->setQuantity(1);
        $orderItem->recalculateTotal();
        $this->em->persist($orderItem);

        // Un retour "demandé" compte ; un retour "refusé" ne compte pas.
        $requested = new ReturnRequest();
        $requested->setOrder($order)->setCustomer($customer)->setReason('x');
        $ri = new ReturnItem();
        $ri->setOrderItem($orderItem)->setQuantity(1);
        $requested->addItem($ri);
        $this->em->persist($ri);
        $this->em->persist($requested);

        $rejected = new ReturnRequest();
        $rejected->setOrder($order)->setCustomer($customer)->setReason('y')->setStatus(ReturnRequest::STATUS_REJECTED);
        $this->em->persist($rejected);

        $this->em->flush();

        $this->client->request('GET', '/api/admin/notifications');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->getJson()['pendingReturns']);
    }

    public function testCountsPendingReviews(): void
    {
        $this->loginAs($this->createAdmin());
        $user    = $this->createUser('client@example.com');
        $product = $this->createProduct('Produit', 'produit', Product::STATUS_PUBLISHED);
        $this->createProductReview($product, $user, 5, false);
        $this->createProductReview($product, $this->createUser('b@example.com'), 4, true);

        $this->client->request('GET', '/api/admin/notifications');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(1, $data['pendingReviews']);
    }
}

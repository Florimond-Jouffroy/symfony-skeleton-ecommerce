<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\Order;
use App\Entity\Product;
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

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\Order;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class OrderTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/commandes');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser('user@example.com'));
        $this->client->request('GET', '/api/admin/commandes');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testTransitionForbiddenForNonAdmin(): void
    {
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer);
        $this->loginAs($this->createUser('user@example.com'));

        $this->postJson('/api/admin/commandes/'.$order->getId().'/transition', ['status' => 'confirmed']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteForbiddenForNonAdmin(): void
    {
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer);
        $this->loginAs($this->createUser('user@example.com'));

        $this->client->request('DELETE', '/api/admin/commandes/'.$order->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function testListReturnsOrders(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $this->createOrder($customer, 'ORD-20240101-00001');
        $this->createOrder($customer, 'ORD-20240101-00002');

        $this->client->request('GET', '/api/admin/commandes');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(2, $data['total']);
        self::assertCount(2, $data['items']);
        self::assertArrayHasKey('orderNumber', $data['items'][0]);
        self::assertArrayHasKey('customer', $data['items'][0]);
        self::assertArrayHasKey('status', $data['items'][0]);
        self::assertArrayHasKey('total', $data['items'][0]);
    }

    public function testListFiltersByStatus(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $this->createOrder($customer, 'ORD-20240101-00001', Order::STATUS_PENDING);
        $this->createOrder($customer, 'ORD-20240101-00002', Order::STATUS_CONFIRMED);

        $this->client->request('GET', '/api/admin/commandes', ['status' => 'confirmed']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(1, $data['total']);
        self::assertSame('ORD-20240101-00002', $data['items'][0]['orderNumber']);
    }

    public function testListSearchByOrderNumber(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $this->createOrder($customer, 'ORD-20240101-00001');
        $this->createOrder($customer, 'ORD-20240101-00002');

        $this->client->request('GET', '/api/admin/commandes', ['q' => '00001']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(1, $data['total']);
        self::assertSame('ORD-20240101-00001', $data['items'][0]['orderNumber']);
    }

    public function testListSearchByCustomerName(): void
    {
        $this->loginAs($this->createAdmin());
        $alice = $this->createCustomer('alice@example.com', 'Alice', 'Martin');
        $bob   = $this->createCustomer('bob@example.com', 'Bob', 'Dupont');
        $this->createOrder($alice, 'ORD-20240101-00001');
        $this->createOrder($bob, 'ORD-20240101-00002');

        $this->client->request('GET', '/api/admin/commandes', ['q' => 'Alice']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(1, $data['total']);
        self::assertSame('ORD-20240101-00001', $data['items'][0]['orderNumber']);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function testGetOrderDetail(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer('jean@example.com', 'Jean', 'Dupont');
        $order    = $this->createOrder($customer);

        $this->client->request('GET', '/api/admin/commandes/'.$order->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame($order->getOrderNumber(), $data['orderNumber']);
        self::assertSame('Jean Dupont', $data['customer']['fullName']);
        self::assertArrayHasKey('items', $data);
        self::assertArrayHasKey('statusHistory', $data);
        self::assertArrayHasKey('allowedTransitions', $data);
        self::assertArrayHasKey('shippingAddress', $data);
    }

    public function testGetOrderDetailIncludesAllowedTransitions(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer, 'ORD-20240101-00001', Order::STATUS_PENDING);

        $this->client->request('GET', '/api/admin/commandes/'.$order->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertContains(Order::STATUS_CONFIRMED, $data['allowedTransitions']);
        self::assertContains(Order::STATUS_CANCELLED, $data['allowedTransitions']);
    }

    public function testGetNonExistentOrderReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('GET', '/api/admin/commandes/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Transition ────────────────────────────────────────────────────────────

    public function testValidTransitionPendingToConfirmed(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer, 'ORD-20240101-00001', Order::STATUS_PENDING);

        $this->postJson('/api/admin/commandes/'.$order->getId().'/transition', [
            'status'  => 'confirmed',
            'comment' => 'Paiement reçu.',
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(Order::STATUS_CONFIRMED, $data['status']);
        self::assertContains(Order::STATUS_SHIPPED, $data['allowedTransitions']);
    }

    public function testTransitionWithInvalidStatusReturns422(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer);

        $this->postJson('/api/admin/commandes/'.$order->getId().'/transition', ['status' => 'foobar']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testForbiddenTransitionReturns422(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        // pending → delivered n'est pas dans TRANSITIONS[pending]
        $order = $this->createOrder($customer, 'ORD-20240101-00001', Order::STATUS_PENDING);

        $this->postJson('/api/admin/commandes/'.$order->getId().'/transition', ['status' => 'delivered']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCancelledOrderHasNoAllowedTransitions(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer, 'ORD-20240101-00001', Order::STATUS_CANCELLED);

        $this->client->request('GET', '/api/admin/commandes/'.$order->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertEmpty($data['allowedTransitions']);
    }

    public function testRefundedOrderHasNoAllowedTransitions(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer, 'ORD-20240101-00001', Order::STATUS_REFUNDED);

        $this->client->request('GET', '/api/admin/commandes/'.$order->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertEmpty($data['allowedTransitions']);
    }

    // ── Note interne ──────────────────────────────────────────────────────────

    public function testUpdateInternalNote(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer);

        $this->patchJson('/api/admin/commandes/'.$order->getId().'/note', [
            'internalNote' => 'À traiter en priorité.',
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('À traiter en priorité.', $data['internalNote']);
    }

    public function testClearInternalNote(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer);

        $this->patchJson('/api/admin/commandes/'.$order->getId().'/note', ['internalNote' => '']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertNull($data['internalNote']);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeleteOrder(): void
    {
        $this->loginAs($this->createAdmin());
        $customer = $this->createCustomer();
        $order    = $this->createOrder($customer);
        $id       = $order->getId();

        $this->client->request('DELETE', '/api/admin/commandes/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(Order::class)->find($id));
    }

    public function testDeleteNonExistentOrderReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->client->request('DELETE', '/api/admin/commandes/99999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\Order;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fiche utilisateur admin : tout utilisateur est un client potentiel, les
 * sections boutique restent vides tant qu'aucune commande n'existe pour son email.
 */
class UserDetailTest extends AbstractApiTestCase
{
    public function testForbiddenForNonAdmin(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->client->request('GET', '/api/admin/utilisateurs/'.$user->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUserWithoutOrdersHasEmptyCustomerProfile(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('sans-commande@example.com');

        $this->client->request('GET', '/api/admin/utilisateurs/'.$user->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();

        self::assertSame('sans-commande@example.com', $data['user']['email']);
        self::assertNull($data['customer']);
        self::assertSame([], $data['orders']);
        self::assertSame([], $data['returns']);
        self::assertSame(0, $data['stats']['orderCount']);
        self::assertSame(0, $data['stats']['totalSpent']);
    }

    public function testUserWithOrdersExposesCustomerProfileAndOrders(): void
    {
        $this->loginAs($this->createAdmin());

        // Le rapprochement User <-> Customer se fait par l'email.
        $email    = 'acheteur@example.com';
        $user     = $this->createUser($email);
        $customer = $this->createCustomer($email, 'Marie', 'Martin');

        $order = $this->createOrder($customer, 'ORD-FICHE-1', Order::STATUS_DELIVERED);
        $order->setTotal(4500);
        $this->em->flush();

        $this->client->request('GET', '/api/admin/utilisateurs/'.$user->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();

        self::assertSame('Marie Martin', $data['customer']['fullName']);
        self::assertSame(1, $data['stats']['orderCount']);
        self::assertSame(4500, $data['stats']['totalSpent']);
        self::assertSame('ORD-FICHE-1', $data['orders'][0]['orderNumber']);
        self::assertSame($order->getId(), $data['orders'][0]['id']);
    }

    public function testCancelledOrdersAreExcludedFromTotalSpent(): void
    {
        $this->loginAs($this->createAdmin());

        $email    = 'annule@example.com';
        $user     = $this->createUser($email);
        $customer = $this->createCustomer($email);

        $paid = $this->createOrder($customer, 'ORD-FICHE-2', Order::STATUS_DELIVERED);
        $paid->setTotal(3000);
        $cancelled = $this->createOrder($customer, 'ORD-FICHE-3', Order::STATUS_CANCELLED);
        $cancelled->setTotal(9999);
        $this->em->flush();

        $this->client->request('GET', '/api/admin/utilisateurs/'.$user->getId());

        self::assertResponseIsSuccessful();
        $data = $this->getJson();

        self::assertSame(2, $data['stats']['orderCount']);
        self::assertSame(3000, $data['stats']['totalSpent']);
    }
}

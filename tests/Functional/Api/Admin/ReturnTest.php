<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
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

    /**
     * La liste admin doit permettre de situer le produit (photo) et de rebondir
     * vers la commande et la fiche du client.
     */
    public function testListExposesImagesAndLinks(): void
    {
        $this->loginAs($this->createAdmin());
        $user   = $this->createUser('client@example.com');
        $return = $this->createReturn(Order::STATUS_DELIVERED, 5, 1);

        $this->client->request('GET', '/api/admin/retours');

        self::assertResponseIsSuccessful();
        $item = $this->getJson()['items'][0];

        self::assertSame($return->getOrder()->getId(), $item['orderId']);
        self::assertSame($user->getId(), $item['customerUserId']);
        self::assertArrayHasKey('imageUrl', $item['items'][0]);
    }

    public function testCustomerWithoutAccountHasNoUserLink(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createReturn(Order::STATUS_DELIVERED, 5, 1);

        $this->client->request('GET', '/api/admin/retours');

        self::assertResponseIsSuccessful();
        self::assertNull($this->getJson()['items'][0]['customerUserId']);
    }

    public function testRejectRequiresAdminNote(): void
    {
        $this->loginAs($this->createAdmin());
        $return = $this->createReturn(Order::STATUS_DELIVERED, 5, 1);

        $this->patchJson('/api/admin/retours/'.$return->getId().'/statut', ['status' => 'rejected']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(ReturnRequest::STATUS_REQUESTED, $return->getStatus());
    }

    public function testRejectPostsMotiveInThread(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createUser('client@example.com');
        $return = $this->createReturn(Order::STATUS_DELIVERED, 5, 1);

        $this->patchJson('/api/admin/retours/'.$return->getId().'/statut', [
            'status'    => 'rejected',
            'adminNote' => 'Délai de retour dépassé.',
        ]);

        self::assertResponseIsSuccessful();
        $messages = $this->getJson()['support']['messages'];

        // Premier message = motif du client, second = refus motivé de l'admin.
        self::assertCount(2, $messages);
        self::assertFalse($messages[0]['isFromAdmin']);
        self::assertSame('Défectueux', $messages[0]['body']);
        self::assertTrue($messages[1]['isFromAdmin']);
        self::assertSame('Délai de retour dépassé.', $messages[1]['body']);
    }

    public function testApproveWithoutNotePostsAutomaticMessage(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createUser('client@example.com');
        $return = $this->createReturn(Order::STATUS_DELIVERED, 5, 1);

        $this->patchJson('/api/admin/retours/'.$return->getId().'/statut', ['status' => 'approved']);

        self::assertResponseIsSuccessful();
        $messages = $this->getJson()['support']['messages'];

        self::assertCount(2, $messages);
        self::assertTrue($messages[1]['isFromAdmin']);
        self::assertStringContainsString('acceptée', $messages[1]['body']);
    }

    public function testAdminCanReplyInThread(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createUser('client@example.com');
        $return = $this->createReturn(Order::STATUS_DELIVERED, 5, 1);

        $this->client->request(
            'POST',
            '/api/admin/retours/'.$return->getId().'/message',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['body' => 'Pouvez-vous nous envoyer une photo ?']),
        );

        self::assertResponseIsSuccessful();
        $messages = $this->getJson()['support']['messages'];

        self::assertCount(2, $messages);
        self::assertSame('Pouvez-vous nous envoyer une photo ?', $messages[1]['body']);
        // Discuter ne change pas le statut de la demande.
        self::assertSame(ReturnRequest::STATUS_REQUESTED, $this->getJson()['status']);
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
        $this->em->flush();

        // Passe par le manager pour que le fil de discussion soit ouvert, comme en vrai.
        $return = static::getContainer()->get(ReturnManager::class)->create(
            $order,
            $customer,
            'Défectueux',
            [['orderItem' => $orderItem, 'quantity' => $qty]],
        );

        self::assertNotNull($return);

        return $return;
    }
}

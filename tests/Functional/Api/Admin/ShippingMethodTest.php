<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\ShippingMethod;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ShippingMethodTest extends AbstractApiTestCase
{
    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/livraison');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateShippingMethod(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/livraison', [
            'name'            => 'Colissimo',
            'description'     => 'Livraison à domicile',
            'price'           => 590,
            'freeAboveAmount' => 5000,
            'isActive'        => true,
            'position'        => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('Colissimo', $data['name']);
        self::assertSame(590, $data['price']);
        self::assertSame(5000, $data['freeAboveAmount']);
    }

    public function testCreateRejectsBlankName(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/livraison', ['name' => '   ', 'price' => 100]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsNegativePrice(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/livraison', ['name' => 'Express', 'price' => -10]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateShippingMethod(): void
    {
        $this->loginAs($this->createAdmin());
        $method = $this->createShippingMethod();

        $this->putJson('/api/admin/livraison/'.$method->getId(), [
            'name'     => 'Point relais',
            'price'    => 390,
            'isActive' => false,
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('Point relais', $data['name']);
        self::assertSame(390, $data['price']);
        self::assertFalse($data['isActive']);
        self::assertNull($data['freeAboveAmount']);
    }

    public function testDeleteShippingMethod(): void
    {
        $this->loginAs($this->createAdmin());
        $method = $this->createShippingMethod();
        $id     = $method->getId();

        $this->client->request('DELETE', '/api/admin/livraison/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(ShippingMethod::class)->find($id));
    }

    private function createShippingMethod(string $name = 'Standard', int $price = 490): ShippingMethod
    {
        $method = new ShippingMethod();
        $method->setName($name);
        $method->setPrice($price);

        $this->em->persist($method);
        $this->em->flush();

        return $method;
    }
}

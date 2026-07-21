<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class SettingTest extends AbstractApiTestCase
{
    public function testGetForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/parametres');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testGetReturnsSettingsPayload(): void
    {
        $this->loginAs($this->createAdmin());
        $this->client->request('GET', '/api/admin/parametres');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertArrayHasKey('shopEnabled', $data);
        self::assertArrayHasKey('invoiceTrigger', $data);
        self::assertArrayHasKey('stripeSecretKeySet', $data);
    }

    public function testPartialUpdateAppliesOnlyProvidedFields(): void
    {
        $this->loginAs($this->createAdmin());

        $this->patchJson('/api/admin/parametres', [
            'maintenanceMode'   => true,
            'twoFaRememberDays' => 60,
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertTrue($data['maintenanceMode']);
        self::assertSame(60, $data['twoFaRememberDays']);
    }

    public function testUpdateRejectsInvalidInvoiceTrigger(): void
    {
        $this->loginAs($this->createAdmin());

        $this->patchJson('/api/admin/parametres', ['invoiceTrigger' => 'on_delivery']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateRejectsOutOfRangeTaxRate(): void
    {
        $this->loginAs($this->createAdmin());

        $this->patchJson('/api/admin/parametres', ['defaultTaxRate' => 150]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateRejectsOutOfRangeTwoFaDays(): void
    {
        $this->loginAs($this->createAdmin());

        $this->patchJson('/api/admin/parametres', ['twoFaRememberDays' => 999]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSecretIsWriteOnlyAndNotWipedByEmptyValue(): void
    {
        $this->loginAs($this->createAdmin());

        // 1. On enregistre un secret Stripe.
        $this->patchJson('/api/admin/parametres', ['stripeSecretKey' => 'sk_test_123']);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->getJson()['stripeSecretKeySet']);

        // 2. Une soumission avec un secret vide ne doit pas l'effacer.
        $this->patchJson('/api/admin/parametres', ['stripeSecretKey' => '']);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->getJson()['stripeSecretKeySet']);

        // 3. Une soumission sans le champ non plus.
        $this->patchJson('/api/admin/parametres', ['shopEnabled' => false]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->getJson()['stripeSecretKeySet']);
    }
}

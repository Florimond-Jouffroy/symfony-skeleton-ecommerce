<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\PromoCode;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class PromoCodeTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/codes-promo');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser());
        $this->client->request('GET', '/api/admin/codes-promo');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function testCreatePercentCode(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/codes-promo', [
            'code'  => 'ete2026',
            'type'  => 'percent',
            'value' => 15,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('ETE2026', $data['code']);
        self::assertSame('percent', $data['type']);
        self::assertSame(15, $data['value']);
        self::assertTrue($data['isActive']);
    }

    public function testCreateFixedCodeWithOptions(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/codes-promo', [
            'code'      => 'BIENVENUE',
            'type'      => 'fixed',
            'value'     => 500,
            'expiresAt' => '2030-12-31',
            'maxUses'   => 100,
            'isActive'  => false,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->getJson();
        self::assertSame('fixed', $data['type']);
        self::assertSame(500, $data['value']);
        self::assertSame('2030-12-31', $data['expiresAt']);
        self::assertSame(100, $data['maxUses']);
        self::assertFalse($data['isActive']);
    }

    // ── Validation (portée par le DTO) ─────────────────────────────────────────

    public function testCreateRejectsBlankCode(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/codes-promo', ['type' => 'percent', 'value' => 10]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsInvalidCodeFormat(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/codes-promo', ['code' => 'bad code!', 'type' => 'percent', 'value' => 10]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsInvalidType(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/codes-promo', ['code' => 'ABC', 'type' => 'gift', 'value' => 10]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsNonPositiveValue(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/codes-promo', ['code' => 'ABC', 'type' => 'fixed', 'value' => 0]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsPercentAbove100(): void
    {
        $this->loginAs($this->createAdmin());

        $this->postJson('/api/admin/codes-promo', ['code' => 'ABC', 'type' => 'percent', 'value' => 150]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsDuplicateCode(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createPromoCode('SOLDE');

        $this->postJson('/api/admin/codes-promo', ['code' => 'solde', 'type' => 'percent', 'value' => 20]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Modification (formulaire complet, option C) ────────────────────────────

    public function testUpdateCode(): void
    {
        $this->loginAs($this->createAdmin());
        $code = $this->createPromoCode('OLD', PromoCode::TYPE_PERCENT, 10);

        $this->patchJson('/api/admin/codes-promo/'.$code->getId(), [
            'code'     => 'OLD',
            'type'     => 'percent',
            'value'    => 25,
            'isActive' => false,
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(25, $data['value']);
        self::assertFalse($data['isActive']);
    }

    public function testUpdateKeepingOwnCodeIsAllowed(): void
    {
        $this->loginAs($this->createAdmin());
        $code = $this->createPromoCode('KEEP', PromoCode::TYPE_PERCENT, 10);

        // Renvoie le même code (comme le toggle front) : ne doit pas déclencher « code existe déjà ».
        $this->patchJson('/api/admin/codes-promo/'.$code->getId(), [
            'code'     => 'KEEP',
            'type'     => 'percent',
            'value'    => 10,
            'isActive' => false,
        ]);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->getJson()['isActive']);
    }

    public function testUpdateRejectsCollisionWithAnotherCode(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createPromoCode('TAKEN');
        $code = $this->createPromoCode('MINE');

        $this->patchJson('/api/admin/codes-promo/'.$code->getId(), [
            'code'  => 'TAKEN',
            'type'  => 'percent',
            'value' => 10,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateNonExistentReturns404(): void
    {
        $this->loginAs($this->createAdmin());

        $this->patchJson('/api/admin/codes-promo/99999', ['code' => 'X', 'type' => 'percent', 'value' => 10]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeleteCode(): void
    {
        $this->loginAs($this->createAdmin());
        $code = $this->createPromoCode('DELME');
        $id   = $code->getId();

        $this->client->request('DELETE', '/api/admin/codes-promo/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(PromoCode::class)->find($id));
    }

    private function createPromoCode(
        string $code = 'PROMO',
        string $type = PromoCode::TYPE_PERCENT,
        int $value = 10,
    ): PromoCode {
        $promo = new PromoCode();
        $promo->setCode($code);
        $promo->setType($type);
        $promo->setValue($value);

        $this->em->persist($promo);
        $this->em->flush();

        return $promo;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Shop;

use App\Entity\PromoCode;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class PromoCodeTest extends AbstractApiTestCase
{
    public function testApplyValidCode(): void
    {
        $this->createUsablePromoCode('SOLDE10');

        $this->postJson('/api/boutique/panier/promo', ['code' => 'solde10']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('SOLDE10', $data['code']);
        self::assertSame(10, $data['value']);
    }

    public function testApplyRejectsMissingCode(): void
    {
        $this->postJson('/api/boutique/panier/promo', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testApplyRejectsUnknownCode(): void
    {
        $this->postJson('/api/boutique/panier/promo', ['code' => 'INCONNU']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function createUsablePromoCode(string $code): PromoCode
    {
        $promo = new PromoCode();
        $promo->setCode($code);
        $promo->setType(PromoCode::TYPE_PERCENT);
        $promo->setValue(10);

        $this->em->persist($promo);
        $this->em->flush();

        return $promo;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api\Shop;

use App\Repository\PromoCodeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/boutique/panier/promo')]
class PromoCodeController extends AbstractController
{
    private const SESSION_KEY = 'shop_promo';

    #[Route('', name: 'api_shop_promo_get', methods: ['GET'])]
    public function get(Request $request, PromoCodeRepository $repo): JsonResponse
    {
        $code = $request->getSession()->get(self::SESSION_KEY);
        if (!$code) {
            return $this->json(null);
        }
        $promo = $repo->findByCode($code);
        if (!$promo || !$promo->isUsable()) {
            $request->getSession()->remove(self::SESSION_KEY);
            return $this->json(null);
        }
        return $this->json([
            'code'  => $promo->getCode(),
            'type'  => $promo->getType(),
            'value' => $promo->getValue(),
        ]);
    }

    #[Route('', name: 'api_shop_promo_apply', methods: ['POST'])]
    public function apply(Request $request, PromoCodeRepository $repo): JsonResponse
    {
        $data    = $request->toArray();
        $rawCode = strtoupper(trim((string) ($data['code'] ?? '')));

        if ('' === $rawCode) {
            return $this->json(['message' => 'Code manquant.'], Response::HTTP_BAD_REQUEST);
        }

        $promo = $repo->findByCode($rawCode);

        if (null === $promo || !$promo->isUsable()) {
            return $this->json(['message' => 'Ce code promo est invalide ou expiré.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->getSession()->set(self::SESSION_KEY, $rawCode);

        return $this->json([
            'code'  => $promo->getCode(),
            'type'  => $promo->getType(),
            'value' => $promo->getValue(),
        ]);
    }

    #[Route('', name: 'api_shop_promo_remove', methods: ['DELETE'])]
    public function remove(Request $request): JsonResponse
    {
        $request->getSession()->remove(self::SESSION_KEY);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

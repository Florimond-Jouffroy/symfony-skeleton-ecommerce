<?php

declare(strict_types=1);

namespace App\Controller\Api\Shop;

use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/boutique/panier')]
class CartController extends AbstractController
{
    private const SESSION_KEY = 'shop_cart';

    #[Route('', name: 'api_shop_cart_get', methods: ['GET'])]
    public function get(Request $request): JsonResponse
    {
        return $this->json($this->serialize($this->getCart($request)));
    }

    #[Route('', name: 'api_shop_cart_add', methods: ['POST'])]
    public function add(
        Request $request,
        ProductRepository $productRepo,
        ProductVariantRepository $variantRepo,
    ): JsonResponse {
        $data      = json_decode($request->getContent(), true) ?? [];
        $productId = (int) ($data['productId'] ?? 0);
        $variantId = isset($data['variantId']) && $data['variantId'] !== null ? (int) $data['variantId'] : null;
        $quantity  = max(1, (int) ($data['quantity'] ?? 1));

        if ($productId <= 0) {
            return $this->json(['message' => 'Produit invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $product = $productRepo->find($productId);
        if (null === $product || !$product->isPublished()) {
            return $this->json(['message' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $variant = null;
        if (null !== $variantId) {
            $variant = $variantRepo->find($variantId);
            if (null === $variant || $variant->getProduct()->getId() !== $product->getId()) {
                return $this->json(['message' => 'Variante introuvable.'], Response::HTTP_NOT_FOUND);
            }
        }

        $coverUrl = null;
        foreach ($product->getImages() as $img) {
            $coverUrl = $img->getUrl();
            break;
        }

        // Vérification du stock
        $availableStock = $variant ? $variant->getStock() : ($product->hasVariants() ? PHP_INT_MAX : $product->getStock());
        if ($availableStock <= 0) {
            return $this->json(['message' => 'Ce produit est en rupture de stock.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $key  = $this->buildKey($productId, $variantId);
        $cart = $this->getCart($request);

        $currentQty    = $cart[$key]['quantity'] ?? 0;
        $quantity      = min($quantity, max(0, $availableStock - $currentQty));

        if ($quantity <= 0) {
            return $this->json(['message' => 'Stock insuffisant pour ajouter cette quantité.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += $quantity;
        } else {
            $cart[$key] = [
                'key'         => $key,
                'productId'   => $productId,
                'variantId'   => $variantId,
                'productName' => $product->getName(),
                'variantName' => $variant?->getName(),
                'slug'        => $product->getSlug(),
                'imageUrl'    => $coverUrl,
                'unitPrice'   => $variant ? $variant->getEffectivePrice() : $product->getPrice(),
                'quantity'    => $quantity,
            ];
        }

        $this->saveCart($request, $cart);

        return $this->json($this->serialize($cart));
    }

    #[Route('/{key}', name: 'api_shop_cart_update', methods: ['PUT'], requirements: ['key' => '[^/]+'])]
    public function update(string $key, Request $request): JsonResponse
    {
        $data     = json_decode($request->getContent(), true) ?? [];
        $quantity = max(0, (int) ($data['quantity'] ?? 0));
        $cart     = $this->getCart($request);

        if (!isset($cart[$key])) {
            return $this->json(['message' => 'Ligne introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($quantity <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['quantity'] = $quantity;
        }

        $this->saveCart($request, $cart);

        return $this->json($this->serialize($cart));
    }

    #[Route('/{key}', name: 'api_shop_cart_remove', methods: ['DELETE'], requirements: ['key' => '[^/]+'])]
    public function remove(string $key, Request $request): JsonResponse
    {
        $cart = $this->getCart($request);
        unset($cart[$key]);
        $this->saveCart($request, $cart);

        return $this->json($this->serialize($cart));
    }

    #[Route('', name: 'api_shop_cart_clear', methods: ['DELETE'])]
    public function clear(Request $request): JsonResponse
    {
        $this->saveCart($request, []);

        return $this->json($this->serialize([]));
    }

    private function buildKey(int $productId, ?int $variantId): string
    {
        return $variantId ? "p{$productId}v{$variantId}" : "p{$productId}";
    }

    private function getCart(Request $request): array
    {
        return $request->getSession()->get(self::SESSION_KEY, []);
    }

    private function saveCart(Request $request, array $cart): void
    {
        $request->getSession()->set(self::SESSION_KEY, $cart);
    }

    private function serialize(array $cart): array
    {
        $items     = array_values($cart);
        $itemCount = (int) array_reduce($items, fn ($c, $i) => $c + $i['quantity'], 0);
        $subtotal  = (int) array_reduce($items, fn ($c, $i) => $c + $i['unitPrice'] * $i['quantity'], 0);

        return [
            'items'     => array_map(
                fn ($item) => array_merge($item, ['lineTotal' => $item['unitPrice'] * $item['quantity']]),
                $items,
            ),
            'itemCount' => $itemCount,
            'subtotal'  => $subtotal,
        ];
    }
}

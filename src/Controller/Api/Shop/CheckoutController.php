<?php

declare(strict_types=1);

namespace App\Controller\Api\Shop;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\PromoCodeRepository;
use App\Repository\ShippingMethodRepository;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/boutique/commande')]
class CheckoutController extends AbstractController
{
    #[Route('', name: 'api_shop_checkout', methods: ['POST'])]
    public function checkout(
        Request $request,
        EntityManagerInterface $em,
        ProductRepository $productRepo,
        ProductVariantRepository $variantRepo,
        CustomerRepository $customerRepo,
        ShippingMethodRepository $shippingRepo,
        OrderRepository $orderRepo,
        PromoCodeRepository $promoRepo,
        InvoiceService $invoiceService,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // ── 1. Validate cart ───────────────────────────────────���──────────────
        $cartItems = $request->getSession()->get('shop_cart', []);
        if (empty($cartItems)) {
            return $this->json(['message' => 'Le panier est vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // ── 2. Parse & validate payload ───────────────────────────────────────
        $data             = $request->toArray();
        $shippingMethodId = (int) ($data['shippingMethodId'] ?? 0);
        $addr             = $data['shippingAddress'] ?? [];
        $customerNote     = isset($data['customerNote']) ? trim((string) $data['customerNote']) : null;

        if ($shippingMethodId <= 0) {
            return $this->json(['message' => 'Méthode de livraison obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $shippingMethod = $shippingRepo->find($shippingMethodId);
        if (null === $shippingMethod || !$shippingMethod->isActive()) {
            return $this->json(['message' => 'Méthode de livraison invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach (['firstName', 'lastName', 'line1', 'city', 'postalCode'] as $field) {
            if ('' === trim((string) ($addr[$field] ?? ''))) {
                return $this->json(['message' => "Le champ « {$field} » est obligatoire."], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // ── 3. Pre-validate all cart items (stock check) ──────────────────────
        $validatedItems = [];
        foreach ($cartItems as $cartItem) {
            $product = $productRepo->find($cartItem['productId'] ?? 0);
            if (null === $product || !$product->isPublished()) {
                return $this->json(['message' => sprintf('Le produit "%s" n\'est plus disponible.', $cartItem['productName'] ?? 'inconnu')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $variant = null;
            if (!empty($cartItem['variantId'])) {
                $variant = $variantRepo->find($cartItem['variantId']);
                if (null === $variant || !$variant->isActive()) {
                    return $this->json(['message' => sprintf('La variante "%s" n\'est plus disponible.', $cartItem['variantName'] ?? 'inconnue')], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $qty   = max(1, (int) ($cartItem['quantity'] ?? 1));
            $stock = $variant ? $variant->getStock() : $product->getStock();

            if ($stock < $qty) {
                $label = $variant
                    ? sprintf('%s – %s', $product->getName(), $variant->getName())
                    : $product->getName();
                return $this->json(['message' => sprintf('Stock insuffisant pour "%s".', $label)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validatedItems[] = [
                'product'   => $product,
                'variant'   => $variant,
                'qty'       => $qty,
                'unitPrice' => (int) ($cartItem['unitPrice'] ?? 0),
                'name'      => $product->getName(),
                'varName'   => $variant?->getName(),
            ];
        }

        // ── 4. Compute subtotal, promo discount & shipping ────────────────────
        $subtotal = array_reduce($validatedItems, fn ($c, $i) => $c + $i['unitPrice'] * $i['qty'], 0);

        $promoCode      = null;
        $discountAmount = 0;
        $promoCodeStr   = $request->getSession()->get('shop_promo');
        if ($promoCodeStr) {
            $promoCode = $promoRepo->findByCode($promoCodeStr);
            if ($promoCode && $promoCode->isUsable()) {
                $discountAmount = $promoCode->computeDiscount($subtotal);
            } else {
                $promoCode = null;
                $request->getSession()->remove('shop_promo');
            }
        }

        $shippingAmount = $shippingMethod->getEffectivePrice($subtotal);

        // ── 5. Find or create Customer ────────────────────────────────────────
        $customer = $customerRepo->findByEmail($user->getEmail());
        if (null === $customer) {
            $customer = new Customer();
            $customer
                ->setEmail($user->getEmail())
                ->setFirstName(trim((string) ($addr['firstName'] ?? '')))
                ->setLastName(trim((string) ($addr['lastName'] ?? '')));
            $em->persist($customer);
        }

        // ── 6. Generate unique order number ───────────────────────────────────
        do {
            $number = sprintf('BM-%s-%s', date('Ymd'), strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)));
        } while (null !== $orderRepo->findOneBy(['orderNumber' => $number]));

        // ── 7. Create Order ───────────────────────────────────────────────────
        $order = new Order();
        $order
            ->setCustomer($customer)
            ->setOrderNumber($number)
            ->setShippingAmount($shippingAmount)
            ->setCustomerNote($customerNote ?: null)
            ->setShippingAddress([
                'firstName'  => trim((string) ($addr['firstName'] ?? '')),
                'lastName'   => trim((string) ($addr['lastName'] ?? '')),
                'line1'      => trim((string) ($addr['line1'] ?? '')),
                'line2'      => trim((string) ($addr['line2'] ?? '')) ?: null,
                'city'       => trim((string) ($addr['city'] ?? '')),
                'postalCode' => trim((string) ($addr['postalCode'] ?? '')),
                'country'    => trim((string) ($addr['country'] ?? 'France')),
            ]);
        $em->persist($order);

        // ── 8. Create OrderItems & decrement stock ────────────────────────────
        $subtotalFinal = 0;
        foreach ($validatedItems as $data) {
            $item = new OrderItem();
            $item
                ->setOrder($order)
                ->setProduct($data['product'])
                ->setVariant($data['variant'])
                ->setProductName($data['name'])
                ->setVariantName($data['varName'])
                ->setUnitPrice($data['unitPrice'])
                ->setQuantity($data['qty']);
            $item->recalculateTotal();
            $subtotalFinal += $item->getTotal();
            $em->persist($item);

            // Decrement stock
            if (null !== $data['variant']) {
                $data['variant']->setStock($data['variant']->getStock() - $data['qty']);
            } else {
                $data['product']->setStock($data['product']->getStock() - $data['qty']);
            }
        }

        $order->setSubtotal($subtotalFinal);
        $order->setDiscountAmount($discountAmount);
        if ($promoCode) {
            $order->setPromoCode($promoCode->getCode());
            $promoCode->incrementUsedCount();
        }
        $order->setTotal(max(0, $subtotalFinal - $discountAmount + $shippingAmount));

        $em->flush();

        // ── 9. Clear cart & promo ─────────────────────────────────────────────
        $request->getSession()->remove('shop_cart');
        $request->getSession()->remove('shop_promo');

        // ── 10. Auto-generate invoice if trigger = on_order ───────────────────
        if ('on_order' === $invoiceService->getInvoiceTrigger()) {
            $invoiceService->generateForOrder($order);
        }

        return $this->json(['orderNumber' => $order->getOrderNumber()], Response::HTTP_CREATED);
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\AppSettingRepository;
use App\Repository\InvoiceRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

class InvoiceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceRepository $invoiceRepo,
        private readonly AppSettingRepository $settingRepo,
        private readonly Environment $twig,
        #[Autowire('%app.company%')] private readonly array $company,
    ) {
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public function getInvoiceTrigger(): string
    {
        return $this->settingRepo->getValue('invoice.trigger', 'on_order');
    }

    public function setInvoiceTrigger(string $trigger): void
    {
        if (!in_array($trigger, ['on_order', 'on_confirm'], true)) {
            throw new \InvalidArgumentException("Trigger invalide : {$trigger}");
        }
        $this->settingRepo->setValue('invoice.trigger', $trigger);
    }

    public function getDefaultTaxRate(): float
    {
        return (float) $this->settingRepo->getValue('invoice.default_tax_rate', '20');
    }

    public function setDefaultTaxRate(float $rate): void
    {
        $this->settingRepo->setValue('invoice.default_tax_rate', (string) $rate);
    }

    // ── Facture ───────────────────────────────────────────────────────────────

    public function findForOrder(Order $order): ?Invoice
    {
        return $this->invoiceRepo->findOneByOrder($order);
    }

    public function generateForOrder(Order $order): Invoice
    {
        $existing = $this->invoiceRepo->findOneByOrder($order);
        if (null !== $existing) {
            return $existing;
        }

        $defaultRate = $this->getDefaultTaxRate();

        // Sous-total HT des articles (avant remise, sans livraison)
        $subtotalHt = 0;
        foreach ($order->getItems() as $item) {
            $rate        = $this->getRateForItem($item, $defaultRate);
            $divisor     = 1 + $rate / 100;
            $subtotalHt += (int) round($item->getTotal() / $divisor);
        }

        // Livraison HT au taux par défaut
        $shippingHt = (int) round($order->getShippingAmount() / (1 + $defaultRate / 100));

        // Détail TVA par taux (avec remise répartie proportionnellement)
        $taxBreakdown = $this->computeTaxBreakdown($order, $defaultRate);

        $totalHt   = (int) array_sum(array_column($taxBreakdown, 'baseHt'));
        $taxAmount = (int) array_sum(array_column($taxBreakdown, 'taxAmount'));
        $totalTtc  = $order->getTotal();

        $invoice = new Invoice();
        $invoice
            ->setInvoiceNumber($this->generateInvoiceNumber())
            ->setOrder($order)
            ->setStatus(Invoice::STATUS_PENDING)
            ->setIssuedAt(new \DateTimeImmutable())
            ->setSubtotalHt($subtotalHt)
            ->setShippingHt($shippingHt)
            ->setDiscountAmount($order->getDiscountAmount())
            ->setTaxBreakdown($taxBreakdown)
            ->setTotalHt($totalHt)
            ->setTaxAmount($taxAmount)
            ->setTotalTtc($totalTtc)
            ->setBillingAddress($order->getBillingAddress() ?? $order->getShippingAddress());

        $this->em->persist($invoice);
        $this->em->flush();

        return $invoice;
    }

    // ── PDF ───────────────────────────────────────────────────────────────────

    public function generatePdf(Invoice $invoice): string
    {
        $order       = $invoice->getOrder();
        $defaultRate = $this->getDefaultTaxRate();

        // Lignes du PDF avec prix unitaire HT par taux
        $lines = [];
        foreach ($order->getItems() as $item) {
            $rate        = $this->getRateForItem($item, $defaultRate);
            $divisor     = 1 + $rate / 100;
            $unitPriceHt = (int) round($item->getUnitPrice() / $divisor);

            $lines[] = [
                'name'         => $item->getProductName(),
                'variant'      => $item->getVariantName(),
                'qty'          => $item->getQuantity(),
                'rate'         => $rate,
                'unitPriceHt'  => $unitPriceHt,
                'unitPriceTtc' => $item->getUnitPrice(),
                'totalHt'      => $unitPriceHt * $item->getQuantity(),
                'totalTtc'     => $item->getTotal(),
            ];
        }

        $html = $this->twig->render('pdf/invoice.html.twig', [
            'invoice' => $invoice,
            'order'   => $order,
            'lines'   => $lines,
            'company' => $this->company,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Calcule le détail TVA par taux, avec répartition proportionnelle de la remise.
     *
     * @return array<array{rate: float, baseHt: int, taxAmount: int}>
     */
    private function computeTaxBreakdown(Order $order, float $defaultRate): array
    {
        // Regrouper les montants TTC par taux
        $groups = []; // rate => ttcAmount

        foreach ($order->getItems() as $item) {
            $rate              = $this->getRateForItem($item, $defaultRate);
            $groups[$rate]     = ($groups[$rate] ?? 0) + $item->getTotal();
        }

        if ($order->getShippingAmount() > 0) {
            $groups[$defaultRate] = ($groups[$defaultRate] ?? 0) + $order->getShippingAmount();
        }

        // Répartir la remise proportionnellement sur chaque groupe de taux
        $discount            = $order->getDiscountAmount();
        $totalBeforeDiscount = (int) array_sum($groups);

        if ($discount > 0 && $totalBeforeDiscount > 0) {
            $applied    = 0;
            $rates      = array_keys($groups);
            $lastRate   = (float) end($rates);

            foreach ($groups as $rate => &$ttc) {
                if ((float) $rate === $lastRate) {
                    // Le dernier groupe absorbe le reste pour éviter un arrondi
                    $ttc -= ($discount - $applied);
                } else {
                    $d        = (int) round($discount * ($ttc / $totalBeforeDiscount));
                    $ttc     -= $d;
                    $applied += $d;
                }
            }
            unset($ttc);
        }

        // Calculer HT et TVA pour chaque groupe
        $breakdown = [];
        foreach ($groups as $rate => $ttc) {
            if ($ttc <= 0) {
                continue;
            }
            $divisor   = 1 + (float) $rate / 100;
            $baseHt    = (int) round($ttc / $divisor);
            $taxAmount = $ttc - $baseHt;

            $breakdown[] = [
                'rate'      => (float) $rate,
                'baseHt'    => $baseHt,
                'taxAmount' => $taxAmount,
            ];
        }

        // Trier par taux décroissant (20% en premier)
        usort($breakdown, static fn (array $a, array $b) => $b['rate'] <=> $a['rate']);

        return $breakdown;
    }

    private function getRateForItem(OrderItem $item, float $defaultRate): float
    {
        $product = $item->getProduct();
        if (null === $product) {
            return $defaultRate;
        }

        foreach ($product->getCategories() as $category) {
            if ($category->getTaxRate() !== null) {
                return (float) $category->getTaxRate();
            }
        }

        return $defaultRate;
    }

    private function generateInvoiceNumber(): string
    {
        $year = (int) date('Y');
        $max  = $this->invoiceRepo->getMaxSequenceForYear($year);

        return sprintf('FACT-%d-%05d', $year, $max + 1);
    }
}

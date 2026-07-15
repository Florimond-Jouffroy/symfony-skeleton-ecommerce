<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Repository\InvoiceRepository;
use App\Security\Voter\InvoiceVoter;
use App\Service\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/factures')]
class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceRepository $invoiceRepo,
    ) {
    }

    #[Route('', name: 'api_admin_invoices_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::VIEW);

        $invoices = $this->invoiceRepo->findAllWithOrder();

        return $this->json(array_map($this->serialize(...), $invoices));
    }

    #[Route('/commande/{id}', name: 'api_admin_invoices_by_order', methods: ['GET'])]
    public function getByOrder(Order $order): JsonResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::VIEW);

        $invoice = $this->invoiceService->findForOrder($order);

        if (null === $invoice) {
            return $this->json(null);
        }

        return $this->json($this->serialize($invoice));
    }

    #[Route('/commande/{id}/generer', name: 'api_admin_invoices_generate', methods: ['POST'])]
    public function generate(Order $order): JsonResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::CREATE);

        $invoice = $this->invoiceService->generateForOrder($order);

        return $this->json($this->serialize($invoice), Response::HTTP_CREATED);
    }

    #[Route('/{id}/pdf', name: 'api_admin_invoices_pdf', methods: ['GET'])]
    public function pdf(Invoice $invoice): StreamedResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::DOWNLOAD);

        $pdfContent = $this->invoiceService->generatePdf($invoice);
        $filename   = $invoice->getInvoiceNumber() . '.pdf';

        return new StreamedResponse(
            static function () use ($pdfContent) { echo $pdfContent; },
            Response::HTTP_OK,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length'      => strlen($pdfContent),
            ],
        );
    }

    #[Route('/{id}/statut', name: 'api_admin_invoices_status', methods: ['PATCH'])]
    public function updateStatus(Invoice $invoice, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::EDIT);

        $payload = $request->toArray();
        $status  = trim((string) ($payload['status'] ?? ''));

        if (!in_array($status, Invoice::STATUSES, true)) {
            return $this->json(['message' => 'Statut invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $invoice->setStatus($status);
        $this->invoiceRepo->getEntityManager()->flush();

        return $this->json($this->serialize($invoice));
    }

    /** @return array<string, mixed> */
    private function serialize(Invoice $invoice): array
    {
        $order = $invoice->getOrder();

        return [
            'id'             => $invoice->getId(),
            'invoiceNumber'  => $invoice->getInvoiceNumber(),
            'status'         => $invoice->getStatus(),
            'statusLabel'    => Invoice::STATUS_LABELS[$invoice->getStatus()] ?? $invoice->getStatus(),
            'issuedAt'       => $invoice->getIssuedAt()->format(\DateTimeInterface::ATOM),
            'subtotalHt'     => $invoice->getSubtotalHt(),
            'shippingHt'     => $invoice->getShippingHt(),
            'discountAmount' => $invoice->getDiscountAmount(),
            'totalHt'        => $invoice->getTotalHt(),
            'taxBreakdown'   => $invoice->getTaxBreakdown(),
            'taxAmount'      => $invoice->getTaxAmount(),
            'totalTtc'       => $invoice->getTotalTtc(),
            'billingAddress' => $invoice->getBillingAddress(),
            'createdAt'      => $invoice->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'order'          => [
                'id'          => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'status'      => $order->getStatus(),
                'customer'    => [
                    'fullName' => $order->getCustomer()->getFullName(),
                    'email'    => $order->getCustomer()->getEmail(),
                ],
            ],
        ];
    }
}

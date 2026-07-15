<?php

declare(strict_types=1);

namespace App\Controller\Api\Shop;

use App\Repository\OrderRepository;
use App\Service\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/boutique/factures')]
class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly OrderRepository $orderRepo,
    ) {
    }

    #[Route('/{orderNumber}', name: 'api_shop_invoice_download', methods: ['GET'])]
    public function download(string $orderNumber): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user  = $this->getUser();
        $order = $this->orderRepo->findOneBy(['orderNumber' => $orderNumber]);

        if (null === $order) {
            return $this->json(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Ownership check: customer email must match logged-in user
        if ($order->getCustomer()->getEmail() !== $user->getEmail()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $invoice = $this->invoiceService->findForOrder($order);

        if (null === $invoice) {
            return $this->json(['message' => 'Aucune facture disponible pour cette commande.'], Response::HTTP_NOT_FOUND);
        }

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
}

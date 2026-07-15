<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Order;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Security\Voter\StatisticsVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/statistiques', name: 'api_admin_stats_', methods: ['GET'])]
class StatisticsController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository    $orderRepo,
        private readonly CustomerRepository $customerRepo,
        private readonly ProductRepository  $productRepo,
    ) {
    }

    #[Route('', name: 'dashboard')]
    public function dashboard(): JsonResponse
    {
        $this->denyAccessUnlessGranted(StatisticsVoter::VIEW);

        $now            = new \DateTimeImmutable();
        $startOfMonth   = new \DateTimeImmutable('first day of this month 00:00:00');
        $startOfNext    = $startOfMonth->modify('+1 month');
        $startOfLast    = $startOfMonth->modify('-1 month');

        $revenueThisMonth = $this->orderRepo->sumRevenue($startOfMonth, $startOfNext);
        $revenueLastMonth = $this->orderRepo->sumRevenue($startOfLast, $startOfMonth);

        $byStatus = $this->orderRepo->countByStatus();

        $recentOrders = array_map(function (Order $o): array {
            $c = $o->getCustomer();
            return [
                'id'          => $o->getId(),
                'orderNumber' => $o->getOrderNumber(),
                'status'      => $o->getStatus(),
                'total'       => $o->getTotal(),
                'createdAt'   => $o->getCreatedAt()->format('c'),
                'customer'    => $c ? ['firstName' => $c->getFirstName(), 'lastName' => $c->getLastName()] : null,
            ];
        }, $this->orderRepo->findRecent(5));

        $revenueByMonth = array_map(fn(array $r): array => [
            'label'   => sprintf('%04d-%02d', $r['year'], $r['month']),
            'revenue' => $r['revenue'],
        ], $this->orderRepo->revenueByMonth(6));

        $lowStockProducts = array_map(fn($p) => [
            'id'                => $p->getId(),
            'name'              => $p->getName(),
            'stock'             => $p->getStock(),
            'lowStockThreshold' => $p->getLowStockThreshold(),
        ], $this->productRepo->findLowStock());

        return $this->json([
            'kpis' => [
                'revenueThisMonth' => $revenueThisMonth,
                'revenueLastMonth' => $revenueLastMonth,
                'ordersThisMonth'  => $this->orderRepo->countRevenue($startOfMonth, $startOfNext),
                'ordersPending'    => $byStatus[Order::STATUS_PENDING] ?? 0,
                'customersTotal'   => $this->customerRepo->count([]),
            ],
            'ordersByStatus'  => $byStatus,
            'revenueByMonth'  => $revenueByMonth,
            'recentOrders'      => $recentOrders,
            'lowStockProducts'  => $lowStockProducts,
        ]);
    }
}

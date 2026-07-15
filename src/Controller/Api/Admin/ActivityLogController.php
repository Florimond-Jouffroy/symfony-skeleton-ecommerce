<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Repository\ActivityLogRepository;
use App\Security\Voter\ActivityLogVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/journal')]
class ActivityLogController extends AbstractController
{
    #[Route('', name: 'api_admin_activity_log_list', methods: ['GET'])]
    public function list(Request $request, ActivityLogRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted(ActivityLogVoter::VIEW);

        $page       = max(1, $request->query->getInt('page', 1));
        $pageSize   = min(100, max(1, $request->query->getInt('pageSize', 50)));
        $entityType = $request->query->getString('entityType') ?: null;
        $fromStr    = $request->query->getString('from') ?: null;
        $toStr      = $request->query->getString('to') ?: null;

        try {
            $from = $fromStr ? new \DateTimeImmutable($fromStr) : null;
            $to   = $toStr   ? (new \DateTimeImmutable($toStr))->setTime(23, 59, 59) : null;
        } catch (\Exception) {
            $from = null;
            $to   = null;
        }

        $result = $repo->findPaginated($entityType, $from, $to, $page, $pageSize);

        return $this->json([
            'items' => array_map(fn ($log) => [
                'id'               => $log->getId(),
                'action'           => $log->getAction(),
                'entityType'       => $log->getEntityType(),
                'entityId'         => $log->getEntityId(),
                'entityLabel'      => $log->getEntityLabel(),
                'context'          => $log->getContext(),
                'performedByEmail' => $log->getPerformedByEmail(),
                'createdAt'        => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], $result['items']),
            'total'    => $result['total'],
            'page'     => $page,
            'pageSize' => $pageSize,
        ]);
    }
}

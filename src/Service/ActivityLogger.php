<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        string $entityLabel,
        array $context = [],
    ): void {
        $user = $this->security->getUser();

        $log = (new ActivityLog())
            ->setAction($action)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setEntityLabel($entityLabel)
            ->setContext($context)
            ->setPerformedByEmail($user?->getUserIdentifier() ?? 'système');

        $this->em->persist($log);
        $this->em->flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;

class PasswordResetManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PasswordResetTokenRepository $tokenRepository,
        private readonly ApplicationLogManager $logManager,
    ) {
    }

    public function createToken(User $user): ?PasswordResetToken
    {
        $this->tokenRepository->deleteByUser($user);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = new PasswordResetToken($user, $code, new \DateTimeImmutable('+15 minutes'));

        $this->em->persist($token);

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logManager->reportException($e);

            return null;
        }

        return $token;
    }

    public function consumeToken(PasswordResetToken $token): bool
    {
        $token->markAsUsed();

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logManager->reportException($e);

            return false;
        }

        return true;
    }
}

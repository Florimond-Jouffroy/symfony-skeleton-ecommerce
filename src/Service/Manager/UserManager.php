<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Dto\RegisterDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationLogManager $logManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function insert(User $entity, bool $flush = true): bool
    {
        $this->em->persist($entity);

        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        return true;
    }

    public function update(User $entity, bool $flush = true): bool
    {
        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        return true;
    }

    public function delete(User $entity, bool $flush = true): bool
    {
        $this->em->remove($entity);

        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        return true;
    }

    public function verifyEmail(User $user, bool $flush = true): bool
    {
        $user->setIsVerified(true);
        $user->setVerificationToken(null);

        return $this->update($user, $flush);
    }

    public function resetPassword(User $user, string $plainPassword, bool $flush = true): bool
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        return $this->update($user, $flush);
    }

    public function createFromDto(RegisterDto $dto, bool $flush = true): ?User
    {
        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        $user->setVerificationToken(bin2hex(random_bytes(32)));

        if ($this->insert($user, $flush)) {
            return $user;
        }

        return null;
    }
}

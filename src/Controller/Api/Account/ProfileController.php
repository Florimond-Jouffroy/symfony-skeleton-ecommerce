<?php

declare(strict_types=1);

namespace App\Controller\Api\Account;

use App\Dto\PasswordChangeDto;
use App\Dto\ProfileUpdateDto;
use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/compte')]
class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'api_account_profile_get', methods: ['GET'])]
    public function get(CustomerRepository $customerRepository): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $customer = $customerRepository->findOneBy(['email' => $user->getEmail()]);

        return $this->json($this->serialize($user->getEmail() ?? '', $customer));
    }

    #[Route('/profil', name: 'api_account_profile_update', methods: ['PUT'])]
    public function update(
        #[MapRequestPayload] ProfileUpdateDto $dto,
        CustomerRepository $customerRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user  = $this->getUser();
        $phone = null !== $dto->phone ? (trim($dto->phone) ?: null) : null;

        $customer = $customerRepository->findOneBy(['email' => $user->getEmail()]);
        if (null === $customer) {
            $customer = new Customer();
            $customer->setEmail($user->getEmail() ?? '');
            $em->persist($customer);
        }

        $customer->setFirstName(trim($dto->firstName));
        $customer->setLastName(trim($dto->lastName));
        $customer->setPhone($phone);
        $em->flush();

        return $this->json($this->serialize($user->getEmail() ?? '', $customer));
    }

    #[Route('/mot-de-passe', name: 'api_account_password_update', methods: ['PUT'])]
    public function changePassword(
        #[MapRequestPayload] PasswordChangeDto $dto,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // La longueur et la confirmation sont validées par le DTO ; ne reste que
        // la vérification du mot de passe actuel, qui exige le hasher.
        if (!$hasher->isPasswordValid($user, $dto->currentPassword)) {
            return $this->json(['message' => 'Mot de passe actuel incorrect.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($hasher->hashPassword($user, $dto->newPassword));
        $em->flush();

        return $this->json(['message' => 'Mot de passe mis à jour.']);
    }

    private function serialize(string $email, ?Customer $customer): array
    {
        return [
            'email'      => $email,
            'firstName'  => $customer?->getFirstName() ?? '',
            'lastName'   => $customer?->getLastName() ?? '',
            'phone'      => $customer?->getPhone(),
            'hasProfile' => null !== $customer,
        ];
    }
}

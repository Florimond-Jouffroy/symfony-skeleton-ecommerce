<?php

declare(strict_types=1);

namespace App\Controller\Api\Account;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

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
        Request $request,
        CustomerRepository $customerRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $payload = $request->toArray();

        $firstName = trim((string) ($payload['firstName'] ?? ''));
        $lastName  = trim((string) ($payload['lastName'] ?? ''));
        $phone     = trim((string) ($payload['phone'] ?? '')) ?: null;

        if ('' === $firstName || '' === $lastName) {
            return $this->json(['message' => 'Le prénom et le nom sont obligatoires.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $customer = $customerRepository->findOneBy(['email' => $user->getEmail()]);
        if (null === $customer) {
            $customer = new Customer();
            $customer->setEmail($user->getEmail() ?? '');
            $em->persist($customer);
        }

        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        $customer->setPhone($phone);
        $em->flush();

        return $this->json($this->serialize($user->getEmail() ?? '', $customer));
    }

    #[Route('/mot-de-passe', name: 'api_account_password_update', methods: ['PUT'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $payload = $request->toArray();

        $current = (string) ($payload['currentPassword'] ?? '');
        $new     = (string) ($payload['newPassword'] ?? '');
        $confirm = (string) ($payload['newPasswordConfirm'] ?? '');

        if (!$hasher->isPasswordValid($user, $current)) {
            return $this->json(['message' => 'Mot de passe actuel incorrect.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($new) < 8) {
            return $this->json(['message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($new !== $confirm) {
            return $this->json(['message' => 'Les mots de passe ne correspondent pas.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $em->flush();

        return $this->json(['message' => 'Mot de passe mis à jour.']);
    }

    private function serialize(string $email, ?Customer $customer): array
    {
        return [
            'email'     => $email,
            'firstName' => $customer?->getFirstName() ?? '',
            'lastName'  => $customer?->getLastName() ?? '',
            'phone'     => $customer?->getPhone(),
            'hasProfile' => $customer !== null,
        ];
    }
}

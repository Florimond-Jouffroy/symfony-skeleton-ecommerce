<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ConfirmPasswordResetDto;
use App\Dto\LoginDto;
use App\Dto\RegisterDto;
use App\Dto\RequestPasswordResetDto;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Security\LoginAuthenticator;
use App\Service\AuthMailer;
use App\Service\Manager\PasswordResetManager;
use App\Service\Manager\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly PasswordResetManager $passwordResetManager,
        private readonly AuthMailer $authMailer,
    ) {
    }

    #[Route('/connexion', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        #[MapRequestPayload] LoginDto $dto,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): JsonResponse {
        $user = $userRepository->findOneBy(['email' => $dto->email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $dto->password)) {
            return $this->json(
                ['message' => 'Identifiants incorrects.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if (!$user->isVerified()) {
            return $this->json(
                ['message' => 'Veuillez vérifier votre adresse e-mail avant de vous connecter.', 'code' => 'email_not_verified'],
                Response::HTTP_FORBIDDEN,
            );
        }

        $security->login($user, LoginAuthenticator::class);

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/inscription', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterDto $dto,
    ): JsonResponse {
        $user = $this->userManager->createFromDto($dto);

        if (!$user) {
            return $this->json(
                ['message' => 'Une erreur est survenue lors de la création du compte.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $this->authMailer->sendVerificationEmail($user);

        return $this->json(
            ['message' => 'Compte créé. Vérifiez votre boîte e-mail pour activer votre compte.'],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/verification-email/renvoyer', name: 'api_auth_verify_email_resend', methods: ['POST'])]
    public function resendVerification(
        #[MapRequestPayload] RequestPasswordResetDto $dto,
        UserRepository $userRepository,
    ): JsonResponse {
        $user = $userRepository->findOneBy(['email' => $dto->email]);

        if ($user && !$user->isVerified() && $user->getVerificationToken()) {
            $this->authMailer->sendVerificationEmail($user);
        }

        return $this->json(['message' => 'Si ce compte existe et n\'est pas encore vérifié, un nouvel e-mail a été envoyé.']);
    }

    #[Route('/reinitialisation-mot-de-passe/demande', name: 'api_auth_reset_password_request', methods: ['POST'])]
    public function requestPasswordReset(
        #[MapRequestPayload] RequestPasswordResetDto $dto,
        UserRepository $userRepository,
    ): JsonResponse {
        $user = $userRepository->findOneBy(['email' => $dto->email]);

        if ($user) {
            $token = $this->passwordResetManager->createToken($user);

            if ($token) {
                $this->authMailer->sendPasswordResetCode($user, $token->getCode());
            }
        }

        return $this->json(['message' => 'Si cette adresse est associée à un compte, un code vous a été envoyé.']);
    }

    #[Route('/reinitialisation-mot-de-passe/confirmation', name: 'api_auth_reset_password_confirm', methods: ['POST'])]
    public function confirmPasswordReset(
        #[MapRequestPayload] ConfirmPasswordResetDto $dto,
        UserRepository $userRepository,
        PasswordResetTokenRepository $tokenRepository,
    ): JsonResponse {
        $user = $userRepository->findOneBy(['email' => $dto->email]);

        if (!$user) {
            return $this->json(
                ['message' => 'Code invalide ou expiré.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $token = $tokenRepository->findValidToken($user, $dto->code);

        if (!$token) {
            return $this->json(
                ['message' => 'Code invalide ou expiré.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->userManager->resetPassword($user, $dto->newPassword);
        $this->passwordResetManager->consumeToken($token);

        return $this->json(['message' => 'Votre mot de passe a été réinitialisé avec succès.']);
    }
}

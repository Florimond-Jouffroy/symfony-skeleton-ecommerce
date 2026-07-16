<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ConfirmPasswordResetDto;
use App\Dto\LoginDto;
use App\Dto\RegisterDto;
use App\Dto\RequestPasswordResetDto;
use App\Dto\TwoFactorVerifyDto;
use App\Repository\AppSettingRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Security\LoginAuthenticator;
use App\Service\AuthMailer;
use App\Service\Manager\PasswordResetManager;
use App\Service\Manager\UserManager;
use App\Service\RateLimiterService;
use App\Service\TrustedDeviceService;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        private readonly AppSettingRepository $settingRepo,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly RateLimiterService $rateLimiter,
    ) {
    }

    #[Route('/connexion', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        #[MapRequestPayload] LoginDto $dto,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): JsonResponse {
        $ip          = $request->getClientIp() ?? 'unknown';
        $maxAttempts = (int) $this->settingRepo->getValue('security.rate_limit.max_attempts', '5');
        $windowSecs  = (int) $this->settingRepo->getValue('security.rate_limit.window_minutes', '15') * 60;

        if (!$this->rateLimiter->isAllowed('login', $ip, $maxAttempts)) {
            return $this->rateLimitResponse('login', $ip);
        }

        $user = $userRepository->findOneBy(['email' => $dto->email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $dto->password)) {
            $this->rateLimiter->hit('login', $ip, $windowSecs);

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

        if ($user->isTotpEnabled()) {
            $trustedDeviceDays = (int) $this->settingRepo->getValue('security.2fa.trusted_device_days', '30');

            if ($trustedDeviceDays > 0 && $this->trustedDeviceService->isTrusted($request, $user)) {
                $security->login($user, LoginAuthenticator::class);

                return $this->json([
                    'id'    => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ]);
            }

            $request->getSession()->set('_2fa_pending', $user->getId());

            return $this->json(['2fa_required' => true, 'trusted_device_days' => $trustedDeviceDays]);
        }

        $this->rateLimiter->reset('login', $ip);
        $security->login($user, LoginAuthenticator::class);

        return $this->json([
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/2fa/verifier', name: 'api_auth_2fa_verify', methods: ['POST'])]
    public function verifyTwoFactor(
        Request $request,
        #[MapRequestPayload] TwoFactorVerifyDto $dto,
        UserRepository $userRepository,
        Security $security,
    ): JsonResponse {
        $ip          = $request->getClientIp() ?? 'unknown';
        $maxAttempts = (int) $this->settingRepo->getValue('security.rate_limit.max_attempts', '5');
        $windowSecs  = (int) $this->settingRepo->getValue('security.rate_limit.window_minutes', '15') * 60;

        if (!$this->rateLimiter->isAllowed('2fa', $ip, $maxAttempts)) {
            return $this->rateLimitResponse('2fa', $ip);
        }

        $userId = $request->getSession()->get('_2fa_pending');

        if (!$userId) {
            return $this->json(['message' => 'Session expirée. Veuillez vous reconnecter.'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $userRepository->find($userId);

        if (!$user || !$user->isTotpEnabled()) {
            return $this->json(['message' => 'Erreur d\'authentification.'], Response::HTTP_UNAUTHORIZED);
        }

        $totp = TOTP::createFromSecret($user->getTotpSecret());

        if (!$totp->verify($dto->code, null, 1)) {
            $this->rateLimiter->hit('2fa', $ip, $windowSecs);

            return $this->json(['message' => 'Code invalide ou expiré.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->rateLimiter->reset('2fa', $ip);
        $request->getSession()->remove('_2fa_pending');
        $security->login($user, LoginAuthenticator::class);

        $response = $this->json([
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);

        if ($dto->rememberDevice) {
            $days = (int) $this->settingRepo->getValue('security.2fa.trusted_device_days', '30');
            if ($days > 0) {
                $this->trustedDeviceService->trust($response, $user, $days);
            }
        }

        return $response;
    }

    #[Route('/inscription', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        #[MapRequestPayload] RegisterDto $dto,
    ): JsonResponse {
        $ip          = $request->getClientIp() ?? 'unknown';
        $maxAttempts = (int) $this->settingRepo->getValue('security.rate_limit.max_attempts', '5');
        $windowSecs  = (int) $this->settingRepo->getValue('security.rate_limit.window_minutes', '15') * 60;

        if (!$this->rateLimiter->isAllowed('register', $ip, $maxAttempts)) {
            return $this->rateLimitResponse('register', $ip);
        }

        $this->rateLimiter->hit('register', $ip, $windowSecs);

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
        Request $request,
        #[MapRequestPayload] RequestPasswordResetDto $dto,
        UserRepository $userRepository,
    ): JsonResponse {
        $ip          = $request->getClientIp() ?? 'unknown';
        $maxAttempts = (int) $this->settingRepo->getValue('security.rate_limit.max_attempts', '5');
        $windowSecs  = (int) $this->settingRepo->getValue('security.rate_limit.window_minutes', '15') * 60;

        if (!$this->rateLimiter->isAllowed('reset', $ip, $maxAttempts)) {
            return $this->rateLimitResponse('reset', $ip);
        }

        $this->rateLimiter->hit('reset', $ip, $windowSecs);

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

    private function rateLimitResponse(string $type, string $ip): JsonResponse
    {
        $retryAfter = $this->rateLimiter->getRetryAfter($type, $ip);
        $minutes    = (int) ceil($retryAfter / 60);

        $response = $this->json(
            ['message' => "Trop de tentatives. Réessayez dans {$minutes} minute(s).", 'retry_after' => $retryAfter],
            Response::HTTP_TOO_MANY_REQUESTS,
        );
        $response->headers->set('Retry-After', (string) $retryAfter);

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\TwoFactorVerifyDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/securite/2fa')]
class TwoFactorController extends AbstractController
{
    public function __construct(private readonly string $appName) {}

    #[Route('', name: 'api_admin_2fa_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->json(['enabled' => $user->isTotpEnabled()]);
    }

    #[Route('/setup', name: 'api_admin_2fa_setup', methods: ['POST'])]
    public function setup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $totp = TOTP::generate(new NativeClock());
        $totp->setLabel($user->getEmail());
        $totp->setIssuer($this->appName);

        $secret = $totp->getSecret();
        $request->getSession()->set('_2fa_setup_secret', $secret);

        return $this->json([
            'secret' => $secret,
            'uri'    => $totp->getProvisioningUri(),
        ]);
    }

    #[Route('/activer', name: 'api_admin_2fa_enable', methods: ['POST'])]
    public function enable(
        Request $request,
        #[MapRequestPayload] TwoFactorVerifyDto $dto,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($user->isTotpEnabled()) {
            return $this->json(['message' => 'La 2FA est déjà activée.'], Response::HTTP_CONFLICT);
        }

        $secret = $request->getSession()->get('_2fa_setup_secret');

        if (!$secret) {
            return $this->json(['message' => 'Session expirée. Relancez la configuration.'], Response::HTTP_CONFLICT);
        }

        $totp = TOTP::createFromSecret($secret, new NativeClock());

        if (!$totp->verify($dto->code, null, 1)) {
            return $this->json(['message' => 'Code invalide. Vérifiez que l\'heure de votre appareil est correcte.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setTotpSecret($secret);
        $em->flush();
        $request->getSession()->remove('_2fa_setup_secret');

        return $this->json(['enabled' => true]);
    }

    #[Route('/desactiver', name: 'api_admin_2fa_disable', methods: ['POST'])]
    public function disable(
        #[MapRequestPayload] TwoFactorVerifyDto $dto,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$user->isTotpEnabled()) {
            return $this->json(['message' => 'La 2FA n\'est pas activée.'], Response::HTTP_CONFLICT);
        }

        $totp = TOTP::createFromSecret($user->getTotpSecret(), new NativeClock());

        if (!$totp->verify($dto->code, null, 1)) {
            return $this->json(['message' => 'Code invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setTotpSecret(null);
        $em->flush();

        return $this->json(['enabled' => false]);
    }
}

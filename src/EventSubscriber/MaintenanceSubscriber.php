<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\AppSettingRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * Affiche une page de maintenance pour les visiteurs non-admin.
 *
 * Priorité 5 : s'exécute après le firewall Symfony (priorité 8)
 * pour que le token de sécurité soit chargé depuis la session.
 *
 * Routes toujours accessibles (même en maintenance) :
 *   /connexion, /deconnexion, /api/auth/* → pour que les admins puissent se connecter
 *   /admin/*, /api/admin/* → accès admin normal
 */
class MaintenanceSubscriber implements EventSubscriberInterface
{
    private ?bool $maintenanceMode = null;

    public function __construct(
        private readonly AppSettingRepository $settingRepo,
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 5]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->isMaintenanceMode()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        // Toujours laisser passer : admin, auth, assets
        if ($this->isAlwaysAllowed($path)) {
            return;
        }

        // Les admins voient le site normalement
        try {
            if ($this->authChecker->isGranted('ROLE_ADMIN')) {
                return;
            }
        } catch (\Throwable) {
            // Token non disponible (rare en priorité 5) → on bloque par sécurité
        }

        $event->setResponse(new Response(
            $this->twig->render('maintenance.html.twig'),
            Response::HTTP_SERVICE_UNAVAILABLE,
        ));
    }

    private function isAlwaysAllowed(string $path): bool
    {
        return str_starts_with($path, '/admin')
            || str_starts_with($path, '/api/admin/')
            || str_starts_with($path, '/connexion')
            || $path === '/deconnexion'
            || str_starts_with($path, '/api/auth/');
    }

    private function isMaintenanceMode(): bool
    {
        if (null === $this->maintenanceMode) {
            $this->maintenanceMode = $this->settingRepo->getValue('site.maintenance', 'false') === 'true';
        }

        return $this->maintenanceMode;
    }
}

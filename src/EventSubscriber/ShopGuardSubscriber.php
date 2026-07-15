<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\AppSettingRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Bloque toutes les routes /boutique/* et /api/boutique/* quand la boutique est désactivée.
 * Les routes admin et les autres pages du site restent accessibles.
 */
class ShopGuardSubscriber implements EventSubscriberInterface
{
    private ?bool $shopEnabled = null;

    public function __construct(
        private readonly AppSettingRepository $settingRepo,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 10]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        // Seules les routes boutique (front) et API boutique sont concernées
        if ($path !== '/boutique' && !str_starts_with($path, '/boutique/') && !str_starts_with($path, '/api/boutique/')) {
            return;
        }

        if ($this->isShopEnabled()) {
            return;
        }

        if (str_starts_with($path, '/api/boutique/')) {
            $event->setResponse(new JsonResponse(
                ['message' => 'La boutique est temporairement désactivée.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            ));
        } else {
            $event->setResponse(new Response(
                $this->twig->render('shop/disabled.html.twig'),
                Response::HTTP_SERVICE_UNAVAILABLE,
            ));
        }
    }

    private function isShopEnabled(): bool
    {
        // Mis en cache pour la durée de la requête (static inutile en PHP-FPM)
        if (null === $this->shopEnabled) {
            $this->shopEnabled = $this->settingRepo->getValue('shop.enabled', 'true') === 'true';
        }

        return $this->shopEnabled;
    }
}

<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\AppSettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Expose les paramètres applicatifs comme globals Twig.
 * Disponible dans tous les templates sans injection manuelle.
 */
class AppSettingExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly AppSettingRepository $settingRepo)
    {
    }

    public function getGlobals(): array
    {
        return [
            'shopEnabled'     => $this->settingRepo->getValue('shop.enabled', 'true') === 'true',
            'maintenanceMode' => $this->settingRepo->getValue('site.maintenance', 'false') === 'true',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppSetting>
 */
class AppSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppSetting::class);
    }

    public function getValue(string $key, string $default = ''): string
    {
        $setting = $this->find($key);
        return $setting?->getValue() ?? $default;
    }

    public function setValue(string $key, string $value): void
    {
        $setting = $this->find($key) ?? (new AppSetting())->setSettingKey($key);
        $setting->setValue($value);
        $this->getEntityManager()->persist($setting);
        $this->getEntityManager()->flush();
    }
}

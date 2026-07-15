<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AppSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppSettingRepository::class)]
class AppSetting
{
    #[ORM\Id]
    #[ORM\Column(length: 100)]
    private string $settingKey = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $value = '';

    public function getSettingKey(): string { return $this->settingKey; }
    public function setSettingKey(string $key): self { $this->settingKey = $key; return $this; }

    public function getValue(): string { return $this->value; }
    public function setValue(string $value): self { $this->value = $value; return $this; }
}

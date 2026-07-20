<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UserRolesDto
{
    /**
     * Rôles demandés — filtrés ensuite contre la liste des rôles attribuables.
     *
     * @var list<string>
     */
    #[Assert\All([new Assert\Type('string')])]
    public array $roles = [];
}

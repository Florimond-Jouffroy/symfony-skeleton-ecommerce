<?php

declare(strict_types=1);

namespace App\Dto;

class ReviewModerationDto
{
    /** Approbation de l'avis (toggle de modération). */
    public bool $isApproved = false;
}

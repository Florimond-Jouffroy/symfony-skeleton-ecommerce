<?php

declare(strict_types=1);

namespace App\Dto\Admin;

class OrderNoteDto
{
    /** Note interne libre. Chaîne vide ramenée à null par le controller. */
    public ?string $internalNote = null;
}

<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Invoice;
use App\Security\PermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/** @extends Voter<string, Invoice|null> */
class InvoiceVoter extends Voter
{
    public const string VIEW     = 'INVOICE_VIEW';
    public const string CREATE   = 'INVOICE_CREATE';
    public const string EDIT     = 'INVOICE_EDIT';
    public const string DOWNLOAD = 'INVOICE_DOWNLOAD';

    private const array ATTRIBUTES = [self::VIEW, self::CREATE, self::EDIT, self::DOWNLOAD];

    public function __construct(private readonly PermissionService $permissionService) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && ($subject instanceof Invoice || null === $subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        return $this->permissionService->hasPermission($attribute, $user->getRoles());
    }
}

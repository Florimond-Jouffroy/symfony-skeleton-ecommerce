<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\PermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/** @extends Voter<string, User|null> */
class UserVoter extends Voter
{
    public const string VIEW = 'USER_VIEW';
    public const string RESET_PASSWORD = 'USER_RESET_PASSWORD';
    public const string VERIFY = 'USER_VERIFY';
    public const string RESEND_VERIFICATION = 'USER_RESEND_VERIFICATION';
    public const string EDIT_ROLES = 'USER_EDIT_ROLES';
    public const string DELETE = 'USER_DELETE';

    private const array ATTRIBUTES = [
        self::VIEW,
        self::RESET_PASSWORD,
        self::VERIFY,
        self::RESEND_VERIFICATION,
        self::EDIT_ROLES,
        self::DELETE,
    ];

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && ($subject instanceof User || null === $subject);
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

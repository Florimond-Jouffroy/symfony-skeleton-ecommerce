<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\SupportTicket;
use App\Security\PermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/** @extends Voter<string, SupportTicket|null> */
class SupportVoter extends Voter
{
    public const string VIEW  = 'SUPPORT_VIEW';
    public const string REPLY = 'SUPPORT_REPLY';
    public const string EDIT  = 'SUPPORT_EDIT';

    private const array ATTRIBUTES = [self::VIEW, self::REPLY, self::EDIT];

    public function __construct(private readonly PermissionService $permissionService) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && ($subject instanceof SupportTicket || null === $subject);
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

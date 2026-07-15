<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\PromoCode;
use App\Security\PermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/** @extends Voter<string, PromoCode|null> */
class PromoCodeVoter extends Voter
{
    public const string VIEW   = 'PROMO_CODE_VIEW';
    public const string CREATE = 'PROMO_CODE_CREATE';
    public const string EDIT   = 'PROMO_CODE_EDIT';
    public const string DELETE = 'PROMO_CODE_DELETE';

    private const array ATTRIBUTES = [self::VIEW, self::CREATE, self::EDIT, self::DELETE];

    public function __construct(private readonly PermissionService $permissionService) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && ($subject instanceof PromoCode || null === $subject);
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

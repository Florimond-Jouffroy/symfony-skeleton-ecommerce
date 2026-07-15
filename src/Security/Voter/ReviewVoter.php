<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ProductReview;
use App\Security\PermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/** @extends Voter<string, ProductReview|null> */
class ReviewVoter extends Voter
{
    public const string VIEW   = 'REVIEW_VIEW';
    public const string EDIT   = 'REVIEW_EDIT';
    public const string DELETE = 'REVIEW_DELETE';

    private const array ATTRIBUTES = [self::VIEW, self::EDIT, self::DELETE];

    public function __construct(private readonly PermissionService $permissionService) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && ($subject instanceof ProductReview || null === $subject);
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

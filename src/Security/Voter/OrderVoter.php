<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Security\PermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Contrôle d'accès pour les opérations sur les commandes.
 *
 * Symfony appelle ce voter à chaque denyAccessUnlessGranted() portant sur un
 * attribut ORDER_*. La décision réelle est déléguée à PermissionService qui lit
 * config/permissions.yaml et vérifie si l'un des rôles de l'utilisateur est
 * autorisé pour cette permission.
 *
 * @extends Voter<string, mixed>
 */
class OrderVoter extends Voter
{
    public const string VIEW   = 'ORDER_VIEW';
    public const string EDIT   = 'ORDER_EDIT';
    public const string DELETE = 'ORDER_DELETE';

    public function __construct(private readonly PermissionService $permissionService)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true);
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

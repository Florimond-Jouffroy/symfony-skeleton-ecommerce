<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Yaml\Yaml;

class PermissionService
{
    /** @var array<string, array{roles: list<string>, description: string, conditions: list<string>}> */
    private array $permissions;

    public function __construct(
        string $projectDir,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
        $path = $projectDir.'/config/permissions.yaml';

        /** @var array{permissions: array<string, array{roles: list<string>, description?: string, conditions?: list<string>}>} $data */
        $data = Yaml::parseFile($path);

        $this->permissions = [];

        foreach ($data['permissions'] as $key => $config) {
            $this->permissions[$key] = [
                'roles' => $config['roles'],
                'description' => $config['description'] ?? '',
                'conditions' => $config['conditions'] ?? [],
            ];
        }
    }

    /**
     * Vérifie si au moins un rôle de l'utilisateur est autorisé pour cette permission.
     *
     * @param array<string> $userRoles
     */
    public function hasPermission(string $permission, array $userRoles): bool
    {
        if (!isset($this->permissions[$permission])) {
            return false;
        }

        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($userRoles);

        return [] !== array_intersect($this->permissions[$permission]['roles'], $reachableRoles);
    }

    /**
     * Retourne les conditions associées à une permission.
     *
     * @return list<string>
     */
    public function getConditions(string $permission): array
    {
        return $this->permissions[$permission]['conditions'] ?? [];
    }

    /**
     * Retourne toutes les permissions d'un rôle donné.
     *
     * @return list<string>
     */
    public function getPermissionsForRole(string $role): array
    {
        $result = [];
        foreach ($this->permissions as $permission => $config) {
            if (in_array($role, $config['roles'], true)) {
                $result[] = $permission;
            }
        }

        return $result;
    }
}

<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\App;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Site;
use Kirby\Cms\User;
use Kirby\Exception\PermissionException;

trait AccessTrait
{
    /**
     * Cross-trait contract supplied by BlueprintsTrait. Declaring these
     * requirements keeps IDE/static analysis accurate while the public facade
     * remains composed from cohesive traits.
     */
    abstract public static function menuId(string $name): string;

    /**
     * @return array<array-key, mixed>
     */
    abstract protected static function readBlueprint(string $file): array;

    private static function currentUser(): ?User
    {
        return App::instance()->user();
    }

    private static function accessRules(array $bp): array|bool|null
    {
        $options = $bp['options'] ?? null;
        $access = null;

        if (is_array($options) && array_key_exists('access', $options)) {
            $access = $options['access'];
        }

        if ($access === null && array_key_exists('access', $bp)) {
            $access = $bp['access'];
        }

        return is_array($access) || is_bool($access) ? $access : null;
    }

    private static function accessOverride(array $bp, User $user): ?bool
    {
        $rules = static::accessRules($bp);
        if ($rules === null || is_bool($rules)) {
            return $rules;
        }

        $role = $user->role()?->id();
        if (is_string($role) && array_key_exists($role, $rules)) {
            return (bool)$rules[$role];
        }

        if (array_key_exists('*', $rules)) {
            return (bool)$rules['*'];
        }

        return null;
    }

    private static function roleAreaPermission(User $user, string $areaId): ?bool
    {
        $permissions = $user->role()?->permissions();
        if ($permissions === null) {
            return null;
        }

        $menuId = static::menuId($areaId);
        $permissionIds = array_values(array_unique([$menuId, $areaId]));

        // Prefer the role blueprint when it defines an explicit rule for either
        // the current prefixed area ID or its legacy unprefixed alias. This is
        // essential when a role denies `*` but still allows the legacy ID: the
        // native permission lookup for the new prefixed ID would otherwise see
        // only the wildcard denial and hide an area that remains explicitly
        // allowed for backwards compatibility.
        foreach (['access', 'areas'] as $category) {
            $blueprintPermission = static::roleBlueprintPermission(
                $user,
                $category,
                $permissionIds
            );

            if ($blueprintPermission === false) {
                return false;
            }

            if ($blueprintPermission === true) {
                continue;
            }

            // No role-blueprint rule exists for this category. Fall back to
            // Kirby's normalized permission registry and require every alias
            // to remain allowed, preserving explicit programmatic denials.
            foreach ($permissionIds as $permissionId) {
                if ($permissions->for($category, $permissionId, true) === false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param list<string> $areaIds
     */
    private static function roleBlueprintPermission(
        User $user,
        string $category,
        array $areaIds
    ): ?bool {
        $roleId = $user->role()?->id();
        if (!is_string($roleId) || $roleId === '') {
            return null;
        }

        $root = App::instance()->root('blueprints');
        if (!is_string($root) || $root === '') {
            return null;
        }

        $file = $root . '/users/' . $roleId . '.yml';
        if (!is_file($file)) {
            return null;
        }

        $data = static::readBlueprint($file);
        $rules = $data['permissions'][$category] ?? null;

        if (is_bool($rules)) {
            return $rules;
        }

        if (!is_array($rules)) {
            return null;
        }

        foreach ($areaIds as $areaId) {
            if (array_key_exists($areaId, $rules)) {
                return (bool)$rules[$areaId];
            }
        }

        if (array_key_exists('*', $rules)) {
            return (bool)$rules['*'];
        }

        return null;
    }

    private static function canReadModel(ModelWithContent $model): bool
    {
        if (method_exists($model, 'isAccessible')) {
            return $model->isAccessible() === true;
        }

        // Site::isAccessible() was introduced in Kirby 5.4. Preserve the
        // earlier Panel access.site contract on Kirby 5.2/5.3.
        if ($model instanceof Site) {
            $permissions = static::currentUser()?->role()?->permissions();
            return $permissions?->for('access', 'site', true) !== false;
        }

        if (method_exists($model, 'isReadable') && $model->isReadable() !== true) {
            return false;
        }

        // Unknown/older model types may not define an access action. In that
        // case keep historical access instead of turning the missing action
        // into an implicit denial.
        return $model->permissions()->can('access', true) === true;
    }

    private static function canUpdateModel(ModelWithContent $model): bool
    {
        return static::canReadModel($model)
            && $model->permissions()->can('update') === true;
    }

    private static function canAccessArea(ModelWithContent $model, array $bp, string $operation): bool
    {
        $user = static::currentUser();
        if ($user === null) {
            return false;
        }

        $areaId = $bp['name'] ?? null;
        if (is_string($areaId) && $areaId !== '') {
            $roleOverride = static::roleAreaPermission($user, $areaId);
            if ($roleOverride === false) {
                return false;
            }
        }

        $override = static::accessOverride($bp, $user);
        if ($override === false) {
            return false;
        }

        return match ($operation) {
            'read' => static::canReadModel($model),
            'update' => static::canUpdateModel($model),
            default => false,
        };
    }

    private static function requireAreaReadAccess(ModelWithContent $model, array $bp): void
    {
        if (!static::canAccessArea($model, $bp, 'read')) {
            throw new PermissionException('Not allowed');
        }
    }

    private static function requireAreaUpdateAccess(ModelWithContent $model, array $bp): void
    {
        if (!static::canAccessArea($model, $bp, 'update')) {
            throw new PermissionException('Not allowed');
        }
    }
}

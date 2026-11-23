<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\App;
use Kirby\Cms\Helpers;
use Kirby\Cms\ModelWithContent;
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

    /**
     * Kirby's native `access` permission of the area's Panel ID decides, as
     * for every other Panel area. The deprecated `areas` category still denies
     * until version 5 removes it.
     */
    private static function roleAreaPermission(User $user, string $areaId): ?bool
    {
        $permissions = $user->role()?->permissions();
        if ($permissions === null) {
            return null;
        }

        $menuId = static::menuId($areaId);
        if ($permissions->for('access', $menuId, true) === false) {
            return false;
        }

        if ($permissions->for('areas', $menuId, true) === false) {
            Helpers::deprecated(
                'The "areas" permission category of Blueprint Areas is deprecated and will be removed in version 5. Deny the area with "access.' . $menuId . '" instead.',
                'blueprint-areas-permissions'
            );

            return false;
        }

        return true;
    }

    private static function canReadModel(ModelWithContent $model): bool
    {
        if (method_exists($model, 'isAccessible')) {
            return $model->isAccessible() === true;
        }

        if (method_exists($model, 'isReadable') && $model->isReadable() !== true) {
            return false;
        }

        // Unknown/older model types may not define an access action. In that
        // case keep historical access instead of turning the missing action
        // into an implicit denial.
        return $model->guards()->isAvailable('access', true) === true;
    }

    private static function canUpdateModel(ModelWithContent $model): bool
    {
        return static::canReadModel($model)
            && $model->guards()->isAvailable('update') === true;
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

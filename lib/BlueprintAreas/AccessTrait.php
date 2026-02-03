<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\App;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Cms\User;
use Kirby\Exception\PermissionException;

trait AccessTrait
{
    private static function currentUser(): ?User
    {
        $user = App::instance()->user();
        return $user instanceof User ? $user : null;
    }

    private static function accessRules(array $bp): ?array
    {
        $options = $bp['options'] ?? null;
        $access = null;

        if (is_array($options) === true && array_key_exists('access', $options) === true) {
            $access = $options['access'];
        }

        if ($access === null && array_key_exists('access', $bp) === true) {
            $access = $bp['access'];
        }

        return is_array($access) ? $access : null;
    }

    private static function accessOverride(array $bp, User $user): ?bool
    {
        $rules = static::accessRules($bp);
        if ($rules === null) {
            return null;
        }

        $role = $user->role()?->id();
        if (is_string($role) === true && array_key_exists($role, $rules) === true) {
            return (bool)$rules[$role];
        }

        if (array_key_exists('*', $rules) === true) {
            return (bool)$rules['*'];
        }

        return null;
    }

    private static function roleAreaPermission(User $user, string $areaId): ?bool
    {
        $roleId = $user->role()?->id();
        if (is_string($roleId) === false || $roleId === '') {
            return null;
        }

        $root = App::instance()->root('blueprints');
        if (is_string($root) === false || $root === '') {
            return null;
        }

        $file = $root . '/users/' . $roleId . '.yml';
        if (is_file($file) === false) {
            return null;
        }

        $data = static::readBlueprint($file);
        if (is_array($data) === false) {
            return null;
        }

        $permissions = $data['permissions'] ?? null;
        if (is_array($permissions) === false) {
            return null;
        }

        $areas = $permissions['areas'] ?? null;
        if (is_array($areas) === false) {
            return null;
        }

        if (array_key_exists($areaId, $areas) === true) {
            return (bool)$areas[$areaId];
        }

        if (array_key_exists('*', $areas) === true) {
            return (bool)$areas['*'];
        }

        return null;
    }

    private static function canAccessModel(ModelWithContent $model, User $user): bool
    {
        $permissions = $user->role()?->permissions();
        if ($permissions === null) {
            return false;
        }

        if ($model instanceof Site) {
            return $permissions->for('access', 'site', true);
        }

        if ($model instanceof Page) {
            return $permissions->for('access', 'pages', true);
        }

        return true;
    }

    private static function canUpdateModel(ModelWithContent $model): bool
    {
        if (method_exists($model, 'permissions') === false) {
            return false;
        }

        return $model->permissions()->update();
    }

    private static function canAccessArea(ModelWithContent $model, array $bp, bool $write): bool
    {
        $user = static::currentUser();
        if ($user === null) {
            return false;
        }

        $areaId = $bp['name'] ?? null;
        if (is_string($areaId) === true && $areaId !== '') {
            $roleOverride = static::roleAreaPermission($user, $areaId);
            if ($roleOverride === false) {
                return false;
            }
        }

        $override = static::accessOverride($bp, $user);
        if ($override === false) {
            return false;
        }

        if (static::canAccessModel($model, $user) === false) {
            return false;
        }

        if ($write === true) {
            return static::canUpdateModel($model);
        }

        return static::canUpdateModel($model);
    }

    private static function requireAreaAccess(ModelWithContent $model, array $bp, bool $write): void
    {
        if (static::canAccessArea($model, $bp, $write) === false) {
            throw new PermissionException('Not allowed');
        }
    }
}

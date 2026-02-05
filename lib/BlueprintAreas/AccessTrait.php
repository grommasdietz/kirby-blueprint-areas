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
        return App::instance()->user();
    }

    private static function accessRules(array $bp): ?array
    {
        $options = $bp['options'] ?? null;
        $access = null;

        if (is_array($options) && array_key_exists('access', $options)) {
            $access = $options['access'];
        }

        if ($access === null && array_key_exists('access', $bp)) {
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
        if (!is_array($data)) {
            return null;
        }

        $permissions = $data['permissions'] ?? null;
        if (!is_array($permissions)) {
            return null;
        }

        $areas = $permissions['areas'] ?? null;
        if (!is_array($areas)) {
            return null;
        }

        if (array_key_exists($areaId, $areas)) {
            return (bool)$areas[$areaId];
        }

        if (array_key_exists('*', $areas)) {
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
        return $model->permissions()->update();
    }

    private static function canAccessArea(ModelWithContent $model, array $bp, bool $write): bool
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

        if (!static::canAccessModel($model, $user)) {
            return false;
        }

        if ($write) {
            return static::canUpdateModel($model);
        }

        return true;
    }

    private static function requireAreaAccess(ModelWithContent $model, array $bp, bool $write): void
    {
        if (!static::canAccessArea($model, $bp, $write)) {
            throw new PermissionException('Not allowed');
        }
    }
}

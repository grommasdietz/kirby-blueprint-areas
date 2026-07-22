<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\Blueprint;
use Kirby\Cms\ModelWithContent;
use Kirby\Exception\NotFoundException;

trait ContextTrait
{
    private const AREA_OPERATION_READ = 'read';
    private const AREA_OPERATION_UPDATE = 'update';

    /**
     * @return array{
     *   name: string,
     *   file: string,
     *   props: array<string, mixed>,
     *   model: ModelWithContent,
     *   blueprint: Blueprint,
     *   layout: array<string, mixed>
     * }
     */
    private static function resolveAreaContext(
        string $name,
        string $operation = self::AREA_OPERATION_READ
    ): array {
        $file = static::blueprintFile($name);
        if ($file === null) {
            throw new NotFoundException('Blueprint not found');
        }

        $props = static::readBlueprint($file);
        $props['name'] = $name;
        $model = static::modelForArea($name, $props);

        match ($operation) {
            self::AREA_OPERATION_READ => static::requireAreaReadAccess($model, $props),
            self::AREA_OPERATION_UPDATE => static::requireAreaUpdateAccess($model, $props),
            default => throw new \LogicException('Unknown area operation: ' . $operation),
        };

        $blueprint = static::blueprintForArea($name, $props, $model);

        return [
            'name' => $name,
            'file' => $file,
            'props' => $props,
            'model' => $model,
            'blueprint' => $blueprint,
            'layout' => static::layoutForBlueprint($blueprint),
        ];
    }
}

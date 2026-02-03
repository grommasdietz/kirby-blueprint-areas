<?php

namespace GrommasDietz\Areas;

require_once __DIR__ . '/BlueprintAreas/AccessTrait.php';
require_once __DIR__ . '/BlueprintAreas/BlueprintsTrait.php';
require_once __DIR__ . '/BlueprintAreas/FormsTrait.php';
require_once __DIR__ . '/BlueprintAreas/ChangesTrait.php';
require_once __DIR__ . '/BlueprintAreas/ViewTrait.php';

/**
 * Render Kirby-style blueprints from site/blueprints/areas/* inside a Panel area,
 * while storing all values on the resolved model (site by default).
 */
final class BlueprintAreas
{
    use BlueprintAreas\AccessTrait;
    use BlueprintAreas\BlueprintsTrait;
    use BlueprintAreas\FormsTrait;
    use BlueprintAreas\ChangesTrait;
    use BlueprintAreas\ViewTrait;

    /**
     * @return array<string, mixed>
     */
    public static function requestValues(): array
    {
        $request = \Kirby\Cms\App::instance()->request();

        $values = $request->get('values');
        if ($values === null) {
            $values = $request->get();
        }

        return is_array($values) ? $values : [];
    }
}

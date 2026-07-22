<?php

namespace GrommasDietz\Areas;

require_once __DIR__ . '/BlueprintAreas/AccessTrait.php';
require_once __DIR__ . '/BlueprintAreas/ContextTrait.php';
require_once __DIR__ . '/BlueprintAreas/ApiTrait.php';
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
    use BlueprintAreas\ContextTrait;
    use BlueprintAreas\ApiTrait;
    use BlueprintAreas\BlueprintsTrait;
    use BlueprintAreas\FormsTrait;
    use BlueprintAreas\ChangesTrait;
    use BlueprintAreas\ViewTrait;
}

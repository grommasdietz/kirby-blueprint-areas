<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Support;

use FilesystemIterator;
use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Boots deterministic Kirby instances for PHP tests.
 *
 * The playground provides content and configuration, while plugin discovery
 * happens in tests/.plugins. Each boot receives unique wrapper paths so Kirby's
 * require_once-based loader executes every plugin again after App::destroy().
 */
final class TestEnvironment
{
    private static int $pluginGeneration = 0;

    /**
     * @param array<string,mixed> $overrides Kirby config overrides for edge cases.
     *        The test-only `testPluginsBefore` and `testPluginsAfter` keys accept
     *        fixture plugin directory names from tests/Fixtures/plugins.
     *        `testBlueprints` accepts relative blueprint paths mapped to YAML.
     */
    public static function boot(array $overrides = []): App
    {
        [
            'errors' => $errorHandlers,
            'exceptions' => $exceptionHandlers,
        ] = static::capturePhpHandlers();

        try {
            App::destroy();

            $paths = static::resolvePaths();
            $testPluginsBefore = static::testPluginSources(
                $paths['tests'],
                $overrides['testPluginsBefore'] ?? []
            );
            $testPluginsAfter = static::testPluginSources(
                $paths['tests'],
                $overrides['testPluginsAfter'] ?? []
            );
            $testBlueprints = static::testBlueprintFixtures(
                $overrides['testBlueprints'] ?? []
            );
            unset(
                $overrides['testPluginsBefore'],
                $overrides['testPluginsAfter'],
                $overrides['testBlueprints']
            );

            static::prepareContent($paths['playground'] . '/content');
            static::prepareCache($paths['cache']);
            static::prepareAccounts($paths['accounts']);
            static::prepareBlueprintSandbox(
                sourceRoot: $paths['playground'] . '/site/blueprints',
                targetRoot: $paths['blueprints'],
                fixtures: $testBlueprints
            );
            static::preparePluginSandbox(
                pluginsRoot: $paths['plugins'],
                projectRoot: $paths['project'],
                pluginsBefore: $testPluginsBefore,
                pluginsAfter: $testPluginsAfter
            );

            $defaults = [
                'roots' => [
                    'index'    => $paths['playground'],
                    'plugins'    => $paths['plugins'],
                    'accounts'   => $paths['accounts'],
                    'blueprints' => $paths['blueprints'],
                ],
                'options' => [
                    'debug'  => false,
                    'whoops' => false,
                    'error'  => false,
                    'cache'  => [
                        'pages' => [
                            'type' => 'file',
                            'root' => $paths['cache'] . '/pages',
                        ],
                    ],
                ],
            ];

            $app = new App(array_replace_recursive($defaults, $overrides));
            static::seedUsers($app);

            return $app;
        } finally {
            static::restorePhpHandlers($errorHandlers, $exceptionHandlers);
        }
    }

    public static function cleanup(): void
    {
        App::destroy();

        $paths = static::resolvePaths();
        Dir::remove($paths['cache']);
        Dir::remove($paths['accounts']);
        Dir::remove($paths['plugins']);
        Dir::remove($paths['blueprints']);
        Dir::make($paths['plugins'], true);
        touch($paths['plugins'] . '/.gitkeep');
    }

    /**
     * @return array{errors:list<callable>, exceptions:list<callable>}
     */
    public static function capturePhpHandlers(): array
    {
        return [
            'errors' => static::activeHandlers('error'),
            'exceptions' => static::activeHandlers('exception'),
        ];
    }

    /**
     * @param list<callable> $errorHandlers
     * @param list<callable> $exceptionHandlers
     */
    public static function restorePhpHandlers(
        array $errorHandlers,
        array $exceptionHandlers
    ): void {
        static::clearHandlerStack('error');
        foreach ($errorHandlers as $handler) {
            set_error_handler($handler);
        }

        static::clearHandlerStack('exception');
        foreach ($exceptionHandlers as $handler) {
            set_exception_handler($handler);
        }
    }

    /**
     * @return array{project:string, tests:string, playground:string, cache:string, plugins:string, accounts:string, blueprints:string}
     */
    private static function resolvePaths(): array
    {
        $testsRoot = realpath(__DIR__ . '/..');
        if ($testsRoot === false) {
            throw new \RuntimeException('Unable to resolve tests directory');
        }

        $projectRoot = realpath($testsRoot . '/..');
        if ($projectRoot === false) {
            throw new \RuntimeException('Unable to resolve project root');
        }

        $playgroundRoot = realpath($projectRoot . '/playground');
        if ($playgroundRoot === false) {
            throw new \RuntimeException('Playground root missing for tests');
        }

        return [
            'project'    => $projectRoot,
            'tests'      => $testsRoot,
            'playground' => $playgroundRoot,
            'cache'      => $playgroundRoot . '/site/cache',
            'plugins'    => $testsRoot . '/.plugins',
            'accounts'   => $playgroundRoot . '/site/accounts',
            'blueprints' => $testsRoot . '/.blueprints',
        ];
    }

    private static function prepareCache(string $cacheRoot): void
    {
        Dir::remove($cacheRoot);
        Dir::make($cacheRoot . '/pages', true);
    }

    /**
     * Removes generated content versions and legacy locks from the playground.
     */
    private static function prepareContent(string $contentRoot): void
    {
        if (is_dir($contentRoot) === false) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($contentRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && $item->getFilename() === '_changes') {
                Dir::remove($item->getPathname());
                continue;
            }

            if ($item->isFile() && $item->getFilename() === '.lock') {
                @unlink($item->getPathname());
            }
        }
    }

    private static function prepareAccounts(string $accountsRoot): void
    {
        if (is_dir($accountsRoot) === false) {
            Dir::make($accountsRoot, true);
        }
    }

    /**
     * Copies tracked playground blueprints into a test-only root. Integration
     * tests may freely add temporary areas and user roles without exposing
     * those fixtures in the browser playground when PHPUnit is interrupted.
     *
     * @param array<string, string> $fixtures
     */
    private static function prepareBlueprintSandbox(
        string $sourceRoot,
        string $targetRoot,
        array $fixtures = []
    ): void {
        Dir::remove($targetRoot);
        Dir::make($targetRoot, true);

        if (is_dir($sourceRoot) === false) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $prefixLength = strlen(rtrim($sourceRoot, '/\\')) + 1;

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), $prefixLength);
            if (!is_string($relative) || $relative === '') {
                continue;
            }

            $target = $targetRoot . '/' . $relative;
            if ($item->isDir()) {
                Dir::make($target, true);
                continue;
            }

            Dir::make(dirname($target), true);
            if (copy($item->getPathname(), $target) === false) {
                throw new \RuntimeException('Unable to copy test blueprint: ' . $relative);
            }
        }

        foreach ($fixtures as $relative => $contents) {
            $target = $targetRoot . '/' . $relative;
            Dir::make(dirname($target), true);

            if (file_put_contents($target, $contents) === false) {
                throw new \RuntimeException('Unable to write test blueprint: ' . $relative);
            }
        }
    }

    /**
     * @param list<string> $pluginsBefore
     * @param list<string> $pluginsAfter
     */
    private static function preparePluginSandbox(
        string $pluginsRoot,
        string $projectRoot,
        array $pluginsBefore,
        array $pluginsAfter
    ): void {
        Dir::remove($pluginsRoot);
        Dir::make($pluginsRoot, true);
        touch($pluginsRoot . '/.gitkeep');

        $generation = ++static::$pluginGeneration;

        foreach ($pluginsBefore as $index => $source) {
            static::writePluginWrapper(
                $pluginsRoot,
                sprintf('100-before-%02d-%04d', $index, $generation),
                $source
            );
        }

        static::writePluginWrapper(
            $pluginsRoot,
            sprintf('500-blueprint-areas-%04d', $generation),
            $projectRoot
        );

        foreach ($pluginsAfter as $index => $source) {
            static::writePluginWrapper(
                $pluginsRoot,
                sprintf('900-after-%02d-%04d', $index, $generation),
                $source
            );
        }
    }

    private static function writePluginWrapper(
        string $pluginsRoot,
        string $directory,
        string $source
    ): void {
        $entry = rtrim($source, '/\\') . '/index.php';
        if (is_file($entry) === false) {
            throw new \RuntimeException('Plugin entry file missing: ' . $entry);
        }

        $pluginRoot = $pluginsRoot . '/' . $directory;
        Dir::make($pluginRoot, true);

        $wrapper = "<?php\n\ndeclare(strict_types=1);\n\nrequire "
            . var_export($entry, true)
            . ";\n";

        if (file_put_contents($pluginRoot . '/index.php', $wrapper) === false) {
            throw new \RuntimeException('Unable to create isolated plugin wrapper');
        }
    }

    /**
     * @return array<string, string>
     */
    private static function testBlueprintFixtures(mixed $fixtures): array
    {
        if (!is_array($fixtures)) {
            return [];
        }

        $validated = [];
        foreach ($fixtures as $relative => $contents) {
            if (!is_string($relative) || !is_string($contents)) {
                throw new \InvalidArgumentException('Invalid test blueprint fixture');
            }

            $relative = str_replace('\\', '/', trim($relative, '/'));
            $segments = explode('/', $relative);
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

            if (
                $relative === ''
                || in_array('', $segments, true)
                || in_array('.', $segments, true)
                || in_array('..', $segments, true)
                || !in_array($extension, ['yml', 'yaml'], true)
            ) {
                throw new \InvalidArgumentException('Invalid test blueprint path: ' . $relative);
            }

            $validated[$relative] = $contents;
        }

        return $validated;
    }

    /**
     * @param mixed $plugins
     * @return list<string>
     */
    private static function testPluginSources(string $testsRoot, mixed $plugins): array
    {
        if (!is_array($plugins)) {
            return [];
        }

        $sources = [];
        foreach ($plugins as $plugin) {
            if (!is_string($plugin) || $plugin === '' || str_contains($plugin, '..')) {
                throw new \InvalidArgumentException('Invalid test fixture plugin name');
            }

            $source = $testsRoot . '/Fixtures/plugins/' . trim($plugin, '/\\');
            if (!is_dir($source)) {
                throw new \RuntimeException('Test fixture plugin directory missing: ' . $source);
            }

            $sources[] = $source;
        }

        return $sources;
    }

    private static function seedUsers(App $app): void
    {
        if ($app->users()->count() > 0) {
            return;
        }

        $app->impersonate('kirby');
        $app->users()->create([
            'email' => 'admin@kirby-blueprint-areas.test',
            'name' => 'Test Admin',
            'role' => 'admin',
            'password' => 'playwright',
            'language' => 'en',
        ]);
        $app->impersonate(null);
    }

    /**
     * @return list<callable>
     */
    private static function activeHandlers(string $type): array
    {
        [$setter, $restorer] = $type === 'exception'
            ? ['set_exception_handler', 'restore_exception_handler']
            : ['set_error_handler', 'restore_error_handler'];

        $handlers = [];

        while (true) {
            $previous = $setter(static fn (): bool => false);
            $restorer();

            if ($previous === null) {
                break;
            }

            $handlers[] = $previous;
            $restorer();
        }

        $handlers = array_reverse($handlers);

        foreach ($handlers as $handler) {
            if (is_callable($handler)) {
                $setter($handler);
            }
        }

        return $handlers;
    }

    private static function clearHandlerStack(string $type): void
    {
        [$setter, $restorer] = $type === 'exception'
            ? ['set_exception_handler', 'restore_exception_handler']
            : ['set_error_handler', 'restore_error_handler'];

        while (true) {
            $current = $setter(static fn (): bool => false);
            $restorer();

            if ($current === null) {
                break;
            }

            $restorer();
        }
    }
}

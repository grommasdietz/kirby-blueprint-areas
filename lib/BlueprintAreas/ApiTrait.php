<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\Api as KirbyApi;
use Kirby\Cms\App;
use Kirby\Exception\Exception;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Http\Route;
use Kirby\Http\Router;
use Throwable;

trait ApiTrait
{
    /**
     * Extracts the canonical `{ values: {...} }` request payload.
     *
     * Direct field maps remain supported by default for backwards compatibility
     * and can be disabled with `api.legacyPayload: false`.
     *
     * @return array<string, mixed>
     */
    public static function requestValues(mixed $payload = null): array
    {
        $payload ??= App::instance()->request()->get();
        if (!is_array($payload)) {
            throw new InvalidArgumentException(message: 'The request payload must be an object');
        }

        if (array_key_exists('values', $payload)) {
            $unknown = array_diff(array_keys($payload), ['values', 'language']);
            if ($unknown !== []) {
                throw new InvalidArgumentException(
                    message: 'Unknown request payload keys: ' . implode(', ', $unknown)
                );
            }

            $values = $payload['values'];
            if (!is_array($values)) {
                throw new InvalidArgumentException(message: 'The "values" payload must be an object');
            }
        } else {
            if (static::legacyPayloadEnabled() !== true) {
                throw new InvalidArgumentException(message: 'The request payload must contain a "values" object');
            }

            $values = $payload;
        }

        static::validateValuesPayload($values);

        return $values;
    }

    private static function legacyPayloadEnabled(): bool
    {
        $options = static::options();
        $api = $options['api'] ?? [];

        return !is_array($api) || ($api['legacyPayload'] ?? true) === true;
    }

    private static function validateValuesPayload(array $values): void
    {
        if ($values !== [] && array_is_list($values)) {
            throw new InvalidArgumentException(message: 'The values payload must use field names as keys');
        }

        $options = static::options();
        $api = is_array($options['api'] ?? null) ? $options['api'] : [];
        $maxDepth = $api['maxPayloadDepth'] ?? 32;

        if (is_int($maxDepth) && $maxDepth > 0 && static::payloadDepth($values) > $maxDepth) {
            throw new InvalidArgumentException(message: 'The values payload is nested too deeply');
        }

        $maxBytes = $api['maxPayloadBytes'] ?? null;
        if (is_int($maxBytes) && $maxBytes > 0) {
            $encoded = json_encode($values);
            if (is_string($encoded) && strlen($encoded) > $maxBytes) {
                throw new InvalidArgumentException(message: 'The values payload is too large');
            }
        }
    }

    private static function payloadDepth(mixed $value, int $depth = 0): int
    {
        if (!is_array($value) || $value === []) {
            return $depth;
        }

        $max = $depth;
        foreach ($value as $item) {
            $max = max($max, static::payloadDepth($item, $depth + 1));
        }

        return $max;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $routes
     */
    private static function callProxyApi(
        array $context,
        array $routes,
        string|null $path,
        KirbyApi $api,
        string $dataKey,
        mixed $data
    ): mixed {
        $request = static::proxyRequest($routes, $path, $api);

        if ($request['operation'] === self::AREA_OPERATION_UPDATE) {
            static::requireAreaUpdateAccess($context['model'], $context['props']);
        }

        $apiData = $api->data();
        if (!is_array($apiData)) {
            $apiData = [];
        }

        $action = $request['route']->action();
        $arguments = $request['route']->arguments();

        // Route matching and authorization have already happened above. Bind
        // the selected route action directly to the cloned API instance so it
        // receives the scoped field/section data without routing the wildcard
        // path a second time.
        $proxy = $api->clone([
            'data' => [...$apiData, $dataKey => $data],
            'requestMethod' => $request['method'],
            'requestData' => $api->requestData(),
        ]);

        return $action->call($proxy, ...$arguments);
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     * @return array{method: string, operation: string, route: Route}
     */
    private static function proxyRequest(array $routes, string|null $path, KirbyApi $api): array
    {
        $method = strtoupper($api->requestMethod() ?? 'GET');
        $allowedMethods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'];

        if (!in_array($method, $allowedMethods, true)) {
            throw new Exception(
                message: 'Unsupported proxy request method: ' . $method,
                httpCode: 405
            );
        }

        $path = static::normalizeProxyPath($path);
        $lookupMethods = $method === 'HEAD' ? ['HEAD', 'GET'] : [$method];
        $route = null;
        $dispatchMethod = $method;
        $previous = null;

        foreach ($lookupMethods as $lookupMethod) {
            try {
                $route = (new Router($routes))->find($path, $lookupMethod);
                $dispatchMethod = $lookupMethod;
                break;
            } catch (Throwable $exception) {
                $previous = $exception;
            }
        }

        if ($route === null) {
            if (static::proxyPathExists($routes, $path)) {
                throw new Exception(
                    message: 'Method not allowed for field or section API route',
                    httpCode: 405,
                    previous: $previous
                );
            }

            throw new NotFoundException(
                message: 'No field or section API route found',
                previous: $previous
            );
        }

        $declaredAccess = $route->attributes()['blueprintAreasAccess'] ?? null;
        $operation = match ($declaredAccess) {
            self::AREA_OPERATION_READ => self::AREA_OPERATION_READ,
            self::AREA_OPERATION_UPDATE => self::AREA_OPERATION_UPDATE,
            default => in_array($method, ['GET', 'HEAD'], true)
                ? self::AREA_OPERATION_READ
                : self::AREA_OPERATION_UPDATE,
        };

        return [
            'method' => $dispatchMethod,
            'operation' => $operation,
            'route' => $route,
        ];
    }

    /**
     * Checks whether any declared route pattern matches the normalized path,
     * independently of its HTTP method. At this point the requested method has
     * already failed, so a matching path means 405 rather than 404.
     *
     * @param array<int, array<string, mixed>> $routes
     */
    private static function proxyPathExists(array $routes, string $path): bool
    {
        $router = new Router($routes);

        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            try {
                $router->find($path, $method);
                return true;
            } catch (Throwable) {
                // Continue until the path has been checked for every supported method.
            }
        }

        return false;
    }

    private static function normalizeProxyPath(string|null $path): string
    {
        $path = trim($path ?? '', '/');
        if ($path === '') {
            return '';
        }

        foreach (explode('/', $path) as $segment) {
            $decoded = $segment;
            for ($pass = 0; $pass < 3; $pass++) {
                $next = rawurldecode($decoded);
                if ($next === $decoded) {
                    break;
                }
                $decoded = $next;
            }

            if (
                $decoded === ''
                || $decoded === '.'
                || $decoded === '..'
                || str_contains($decoded, '/')
                || str_contains($decoded, '\\')
                || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1
            ) {
                throw new NotFoundException('Invalid field or section API path');
            }
        }

        return $path;
    }
}

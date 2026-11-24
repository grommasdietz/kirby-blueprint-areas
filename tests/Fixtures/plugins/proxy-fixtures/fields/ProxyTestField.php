<?php

namespace Tests\BlueprintAreas;

final class ProxyTestField extends \Kirby\Form\Field\InputField
{
    protected mixed $value = null;

    public function api(): array
    {
        return [
            [
                'pattern' => 'read-post',
                'method' => 'POST',
                'blueprintAreasAccess' => 'read',
                'action' => function (): array {
                    return [
                        'route' => 'read-post',
                        'method' => $this->requestMethod(),
                        'data' => $this->requestData(),
                    ];
                },
            ],
            [
                'pattern' => 'write-get',
                'method' => 'GET',
                'blueprintAreasAccess' => 'update',
                'action' => function (): array {
                    return [
                        'route' => 'write-get',
                        'method' => $this->requestMethod(),
                    ];
                },
            ],
            [
                'pattern' => 'write-post',
                'method' => 'POST',
                'action' => function (): array {
                    return [
                        'route' => 'write-post',
                        'method' => $this->requestMethod(),
                    ];
                },
            ],
            [
                'pattern' => 'nested/(:any)',
                'method' => 'GET',
                'action' => function (string $value): array {
                    return [
                        'route' => 'nested',
                        'value' => $value,
                        'method' => $this->requestMethod(),
                    ];
                },
            ],
            [
                'pattern' => 'echo/(:any)',
                'method' => 'POST',
                'action' => function (string $value): array {
                    return [
                        'route' => 'echo',
                        'value' => $value,
                        'method' => $this->requestMethod(),
                        'data' => $this->requestData(),
                    ];
                },
            ],
        ];
    }
}

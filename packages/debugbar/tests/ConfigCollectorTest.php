<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Path\ProjectPaths;
use Marko\Debugbar\Debugbar;
use Marko\Debugbar\Storage\DebugbarStorage;

function makeDebugbarWithConfig(array $appConfig): Debugbar
{
    $basePath = sys_get_temp_dir().'/marko-debugbar-config-test-'.bin2hex(random_bytes(4));

    $config = array_replace_recursive([
        'debugbar' => [
            'enabled' => true,
            'inject' => true,
            'capture_cli' => false,
            'theme' => 'auto',
            'route' => [
                'open' => false,
                'allowed_ips' => ['127.0.0.1', '::1'],
            ],
            'storage' => [
                'enabled' => false,
                'path' => $basePath.'/storage/debugbar',
                'max_files' => 100,
            ],
            'collectors' => [
                'messages' => false,
                'time' => false,
                'memory' => false,
                'request' => false,
                'response' => false,
                'inertia' => false,
                'views' => false,
                'database' => false,
                'logs' => false,
                'config' => true,
            ],
            'options' => [
                'messages' => ['trace' => false],
                'config' => [
                    'masked' => [
                        'key',
                        '*.key',
                        'password',
                        '*.password',
                        'secret',
                        '*.secret',
                        'token',
                        '*.token',
                        'dsn',
                        '*.dsn',
                        '*.api_key',
                        '*.private_key',
                    ],
                ],
                'database' => [
                    'with_bindings' => true,
                    'slow_threshold_ms' => 100,
                ],
            ],
        ],
    ], $appConfig);

    $repository = new ConfigRepository($config);
    $storage = new DebugbarStorage($repository, new ProjectPaths($basePath));

    return new Debugbar($repository, $storage);
}

it('masks a top-level password config key', function (): void {
    $debugbar = makeDebugbarWithConfig(['password' => 'hunter2']);

    $dataset = $debugbar->collect();

    expect($dataset['collectors']['config']['config']['password'])->toBe('[masked]');
});

it('masks a top-level secret config key', function (): void {
    $debugbar = makeDebugbarWithConfig(['secret' => 'mysecret']);

    $dataset = $debugbar->collect();

    expect($dataset['collectors']['config']['config']['secret'])->toBe('[masked]');
});

it('masks a dsn config key', function (): void {
    $debugbar = makeDebugbarWithConfig(['dsn' => 'mysql://root@localhost/mydb']);

    $dataset = $debugbar->collect();

    expect($dataset['collectors']['config']['config']['dsn'])->toBe('[masked]');
});

it('still masks a nested password key', function (): void {
    $debugbar = makeDebugbarWithConfig(['database' => ['password' => 'dbpass']]);

    $dataset = $debugbar->collect();

    expect($dataset['collectors']['config']['config']['database']['password'])->toBe('[masked]');
});

it('leaves non-secret config keys visible', function (): void {
    $debugbar = makeDebugbarWithConfig([
        'app' => ['name' => 'Marko', 'debug' => true],
        'cache' => ['driver' => 'file'],
    ]);

    $dataset = $debugbar->collect();

    expect($dataset['collectors']['config']['config']['app']['name'])->toBe('Marko')
        ->and($dataset['collectors']['config']['config']['app']['debug'])->toBeTrue()
        ->and($dataset['collectors']['config']['config']['cache']['driver'])->toBe('file');
});

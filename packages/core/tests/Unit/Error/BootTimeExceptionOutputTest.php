<?php

declare(strict_types=1);

/**
 * Boots a throwaway project in a child PHP process whose only app module throws from its
 * boot callback, with display_errors on (PHP's built-in default without a php.ini).
 *
 * @param array<string, string> $env Extra environment variables for the child
 * @return array{exitCode: int, output: string, log: string, basePath: string}
 */
function runBootTimeExceptionProject(
    array $env = [],
    ?string $dotEnv = null,
): array {
    $root = dirname(__DIR__, 5);
    $basePath = sys_get_temp_dir() . '/marko-boot-error-' . bin2hex(random_bytes(8));
    $moduleDir = $basePath . '/app/broken';
    mkdir($moduleDir, 0755, true);
    mkdir($basePath . '/vendor');
    mkdir($basePath . '/modules');

    file_put_contents($moduleDir . '/composer.json', json_encode([
        'name' => 'app/broken',
        'extra' => ['marko' => ['module' => true]],
    ]));
    file_put_contents($moduleDir . '/module.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        return [
            'boot' => function (): void {
                throw new RuntimeException('SECRET-BOOT-DETAIL <b>tag</b>');
            },
        ];
        PHP);

    if ($dotEnv !== null) {
        file_put_contents($basePath . '/.env', $dotEnv);
    }

    $script = $basePath . '/boot.php';
    file_put_contents($script, '<?php
declare(strict_types=1);
require ' . var_export($root . '/vendor/autoload.php', true) . ';
$base = ' . var_export($basePath, true) . ';
$app = new Marko\Core\Application(vendorPath: $base . "/vendor", modulesPath: $base . "/modules", appPath: $base . "/app");
$app->initialize();
');

    $logFile = $basePath . '/error.log';

    $environment = array_filter(
        getenv(),
        fn (string $value, string $name): bool => !in_array(
            $name,
            ['PARATEST', 'TEST_TOKEN', 'UNIQUE_TEST_TOKEN', 'APP_ENV', 'MARKO_ENV'],
            true,
        )
            && !str_starts_with($name, 'PEST_PARALLEL'),
        ARRAY_FILTER_USE_BOTH,
    );
    $environment = array_merge($environment, $env);

    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=1', '-d', 'log_errors=1', '-d', 'error_log=' . $logFile, $script],
        [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        $basePath,
        $environment,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the boot subprocess.');
    }

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $exitCode = proc_close($process);
    $log = is_file($logFile) ? (string) file_get_contents($logFile) : '';

    removeBootTimeExceptionProject($basePath);

    return ['exitCode' => $exitCode, 'output' => $output, 'log' => $log, 'basePath' => $basePath];
}

function removeBootTimeExceptionProject(
    string $directory,
): void {
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($directory);
}

it('prints no stack trace, paths or message for a boot-time exception when APP_ENV is unset', function (): void {
    ['exitCode' => $exitCode, 'output' => $output, 'log' => $log, 'basePath' => $basePath] = runBootTimeExceptionProject();

    expect($exitCode)->not->toBe(0)
        ->and(str_contains($output, 'Server Error'))->toBeTrue($output)
        ->and(str_contains($output, 'Stack trace'))->toBeFalse($output)
        ->and(str_contains($output, 'SECRET-BOOT-DETAIL'))->toBeFalse($output)
        ->and(str_contains($output, $basePath))->toBeFalse($output)
        ->and(str_contains($output, 'Fatal error'))->toBeFalse($output)
        ->and(str_contains($log, 'SECRET-BOOT-DETAIL'))->toBeTrue($log)
        ->and(str_contains($log, 'Stack trace'))->toBeTrue($log);
});

it('shows the escaped message for a boot-time exception in development', function (): void {
    ['output' => $output] = runBootTimeExceptionProject(['APP_ENV' => 'development']);

    expect(str_contains($output, 'SECRET-BOOT-DETAIL &lt;b&gt;tag&lt;/b&gt;'))->toBeTrue($output)
        ->and(str_contains($output, 'Stack trace'))->toBeFalse($output);
});

it('honours a development environment set only by the .env file', function (): void {
    ['output' => $output] = runBootTimeExceptionProject(dotEnv: "APP_ENV=development\n");

    expect(str_contains($output, 'SECRET-BOOT-DETAIL'))->toBeTrue($output);
});

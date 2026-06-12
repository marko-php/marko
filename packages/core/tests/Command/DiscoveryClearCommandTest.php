<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Commands\DiscoveryClearCommand;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Path\ProjectPaths;

beforeEach(function (): void {
    $this->originalCachePath = array_key_exists('DISCOVERY_CACHE_PATH', $_ENV)
        ? $_ENV['DISCOVERY_CACHE_PATH']
        : null;
});

afterEach(function (): void {
    if ($this->originalCachePath === null) {
        unset($_ENV['DISCOVERY_CACHE_PATH']);
    } else {
        $_ENV['DISCOVERY_CACHE_PATH'] = $this->originalCachePath;
    }
});

function makeDiscoveryClearSetup(string $cacheDir): array
{
    $cachePath = $cacheDir . '/discovery.php';
    $_ENV['DISCOVERY_CACHE_PATH'] = $cachePath;
    $paths = new ProjectPaths($cacheDir);
    $env = new DiscoveryEnvironment();
    $cache = new DiscoveryCache($paths, $env);
    $command = new DiscoveryClearCommand($cache);
    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    return [
        'cache' => $cache,
        'command' => $command,
        'stream' => $stream,
        'input' => $input,
        'output' => $output,
        'path' => $cachePath,
    ];
}

it('removes an existing cache file and returns exit code 0', function (): void {
    $dir = sys_get_temp_dir() . '/marko_clear_test_' . uniqid('', true);
    mkdir($dir, 0755, true);
    $setup = makeDiscoveryClearSetup($dir);

    // Create the cache file
    file_put_contents($setup['path'], '<?php return [];');

    $exitCode = $setup['command']->execute($setup['input'], $setup['output']);

    expect($exitCode)->toBe(0)
        ->and(file_exists($setup['path']))->toBeFalse();

    fclose($setup['stream']);
    rmdir($dir);
});

it('reports the cache as cleared via writeLine', function (): void {
    $dir = sys_get_temp_dir() . '/marko_clear_test_' . uniqid('', true);
    mkdir($dir, 0755, true);
    $setup = makeDiscoveryClearSetup($dir);

    file_put_contents($setup['path'], '<?php return [];');

    $setup['command']->execute($setup['input'], $setup['output']);

    rewind($setup['stream']);
    $result = stream_get_contents($setup['stream']);

    expect($result)->toContain('Discovery cache cleared');

    fclose($setup['stream']);
    rmdir($dir);
});

it('returns exit code 0 and reports success when no cache file exists', function (): void {
    $dir = sys_get_temp_dir() . '/marko_clear_test_' . uniqid('', true);
    mkdir($dir, 0755, true);
    $setup = makeDiscoveryClearSetup($dir);

    // Do NOT create the cache file
    $exitCode = $setup['command']->execute($setup['input'], $setup['output']);

    rewind($setup['stream']);
    $result = stream_get_contents($setup['stream']);

    expect($exitCode)->toBe(0)
        ->and($result)->toContain('Discovery cache cleared');

    fclose($setup['stream']);
    rmdir($dir);
});

it('leaves DiscoveryCache exists() false after running', function (): void {
    $dir = sys_get_temp_dir() . '/marko_clear_test_' . uniqid('', true);
    mkdir($dir, 0755, true);
    $setup = makeDiscoveryClearSetup($dir);

    file_put_contents($setup['path'], '<?php return [];');

    $setup['command']->execute($setup['input'], $setup['output']);

    expect($setup['cache']->exists())->toBeFalse();

    fclose($setup['stream']);
    rmdir($dir);
});

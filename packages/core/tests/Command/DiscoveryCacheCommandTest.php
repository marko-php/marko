<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Commands\DiscoveryCacheCommand;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Module\ModuleRepositoryInterface;
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

function makeDiscoveryCacheSetup(string $cacheDir): array
{
    $cachePath = $cacheDir . '/discovery.php';
    $_ENV['DISCOVERY_CACHE_PATH'] = $cachePath;
    $paths = new ProjectPaths($cacheDir);
    $env = new DiscoveryEnvironment();
    $cache = new DiscoveryCache($paths, $env);
    $compiler = new DiscoveryCompiler();
    $moduleRepository = new class () implements ModuleRepositoryInterface
    {
        public function all(): array
        {
            return [];
        }
    };
    $command = new DiscoveryCacheCommand($compiler, $cache, $moduleRepository);
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
        'dir' => $cacheDir,
    ];
}

function discoveryCacheTestCleanup(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($dir);
}

it('compiles discovery and writes the cache file, returning exit code 0', function (): void {
    $dir = sys_get_temp_dir() . '/marko_cache_cmd_test_' . uniqid('', true);
    $setup = makeDiscoveryCacheSetup($dir);

    $exitCode = $setup['command']->execute($setup['input'], $setup['output']);

    expect($exitCode)->toBe(0)
        ->and(file_exists($setup['path']))->toBeTrue();

    fclose($setup['stream']);
    discoveryCacheTestCleanup($dir);
});

it('writes a cache file that DiscoveryCache reports as existing after the command runs', function (): void {
    $dir = sys_get_temp_dir() . '/marko_cache_cmd_test_' . uniqid('', true);
    $setup = makeDiscoveryCacheSetup($dir);

    $setup['command']->execute($setup['input'], $setup['output']);

    expect($setup['cache']->exists())->toBeTrue();

    fclose($setup['stream']);
    discoveryCacheTestCleanup($dir);
});

it(
    'reports the cache file path and the counts of cached preferences, plugins, observers, and commands',
    function (): void {
        $dir = sys_get_temp_dir() . '/marko_cache_cmd_test_' . uniqid('', true);
        $setup = makeDiscoveryCacheSetup($dir);

        $setup['command']->execute($setup['input'], $setup['output']);

        rewind($setup['stream']);
        $result = stream_get_contents($setup['stream']);

        expect($result)->toContain($setup['path'])
            ->and($result)->toContain('preferences')
            ->and($result)->toContain('plugins')
            ->and($result)->toContain('observers')
            ->and($result)->toContain('commands');

        fclose($setup['stream']);
        discoveryCacheTestCleanup($dir);
    },
);

it(
    'returns a non-zero exit code and a helpful message (catching DiscoveryCacheException::notWritable) when the cache cannot be written',
    function (): void {
        $dir = sys_get_temp_dir() . '/marko_cache_cmd_test_' . uniqid('', true);
        // Create a read-only directory so the write will fail
        mkdir($dir, 0555, true);
        $cachePath = $dir . '/discovery.php';
        $_ENV['DISCOVERY_CACHE_PATH'] = $cachePath;
        $paths = new ProjectPaths($dir);
        $env = new DiscoveryEnvironment();
        $cache = new DiscoveryCache($paths, $env);
        $compiler = new DiscoveryCompiler();
        $moduleRepository = new class () implements ModuleRepositoryInterface
        {
            public function all(): array
            {
                return [];
            }
        };
        $command = new DiscoveryCacheCommand($compiler, $cache, $moduleRepository);
        $stream = fopen('php://memory', 'r+');
        $input = new Input([]);
        $output = new Output($stream);

        $exitCode = $command->execute($input, $output);

        rewind($stream);
        $result = stream_get_contents($stream);

        expect($exitCode)->not->toBe(0)
            ->and($result)->not->toBeEmpty();

        fclose($stream);
        chmod($dir, 0755);
        discoveryCacheTestCleanup($dir);
    },
);

it('overwrites a pre-existing cache file with freshly compiled content', function (): void {
    $dir = sys_get_temp_dir() . '/marko_cache_cmd_test_' . uniqid('', true);
    mkdir($dir, 0755, true);
    $setup = makeDiscoveryCacheSetup($dir);

    // Write stale content
    file_put_contents($setup['path'], '<?php return ["stale" => true];');

    $exitCode = $setup['command']->execute($setup['input'], $setup['output']);

    $content = (string) file_get_contents($setup['path']);

    expect($exitCode)->toBe(0)
        ->and($content)->not->toContain('"stale"')
        ->and($content)->toContain('return ');

    fclose($setup['stream']);
    discoveryCacheTestCleanup($dir);
});

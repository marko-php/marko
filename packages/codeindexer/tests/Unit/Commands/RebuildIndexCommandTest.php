<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Commands\RebuildIndexCommand;
use Marko\CodeIndexer\Exceptions\IndexCacheException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

// ── helpers ──────────────────────────────────────────────────────────────────

function makeStubCache(
    array $modules = [],
    array $observers = [],
    array $plugins = [],
    array $preferences = [],
    array $commands = [],
    array $routes = [],
    array $configKeys = [],
    array $templates = [],
    array $translationKeys = [],
): IndexCache {
    return new class (
        $modules,
        $observers,
        $plugins,
        $preferences,
        $commands,
        $routes,
        $configKeys,
        $templates,
        $translationKeys,
    ) extends IndexCache
    {
        public function __construct(
            private array $stubModules,
            private array $stubObservers,
            private array $stubPlugins,
            private array $stubPreferences,
            private array $stubCommands,
            private array $stubRoutes,
            private array $stubConfigKeys,
            private array $stubTemplates,
            private array $stubTranslationKeys,
        ) {
            // skip parent constructor — no real dependencies needed
        }

        public function build(): void {}

        public function getModules(): array
        {
            return $this->stubModules;
        }

        public function getObservers(): array
        {
            return $this->stubObservers;
        }

        public function getPlugins(): array
        {
            return $this->stubPlugins;
        }

        public function getPreferences(): array
        {
            return $this->stubPreferences;
        }

        public function getCommands(): array
        {
            return $this->stubCommands;
        }

        public function getRoutes(): array
        {
            return $this->stubRoutes;
        }

        public function getConfigKeys(): array
        {
            return $this->stubConfigKeys;
        }

        public function getTemplates(): array
        {
            return $this->stubTemplates;
        }

        public function getTranslationKeys(): array
        {
            return $this->stubTranslationKeys;
        }
    };
}

function makeThrowingCache(): IndexCache
{
    return new class () extends IndexCache
    {
        public function __construct()
        {
            // skip parent constructor
        }

        public function build(): void
        {
            throw IndexCacheException::cacheDirUnwritable('/.marko');
        }
    };
}

// ── tests ─────────────────────────────────────────────────────────────────────

it('is registered via the Command attribute with name indexer:rebuild', function (): void {
    $reflection = new ReflectionClass(RebuildIndexCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('indexer:rebuild');
});

it('implements CommandInterface', function (): void {
    expect(RebuildIndexCommand::class)->toImplement(CommandInterface::class);
});

it('invokes IndexCache::rebuild and writes to disk', function (): void {
    $invoked = false;
    $cache = new class ($invoked) extends IndexCache
    {
        public function __construct(private bool &$invoked)
        {
            // skip parent constructor
        }

        public function build(): void
        {
            $this->invoked = true;
        }

        public function getModules(): array
        {
            return [];
        }

        public function getObservers(): array
        {
            return [];
        }

        public function getPlugins(): array
        {
            return [];
        }

        public function getPreferences(): array
        {
            return [];
        }

        public function getCommands(): array
        {
            return [];
        }

        public function getRoutes(): array
        {
            return [];
        }

        public function getConfigKeys(): array
        {
            return [];
        }

        public function getTemplates(): array
        {
            return [];
        }

        public function getTranslationKeys(): array
        {
            return [];
        }
    };

    $stream = fopen('php://memory', 'w+');
    $output = new Output($stream);
    $command = new RebuildIndexCommand($cache);

    $result = $command->execute(new Input([]), $output);

    fclose($stream);

    expect($result)->toBe(0)
        ->and($invoked)->toBeTrue();
});

it('prints a summary of indexed counts', function (): void {
    $cache = makeStubCache(
        modules: ['a', 'b', 'c'],
        observers: ['x'],
        plugins: ['p1', 'p2'],
        preferences: [],
        commands: ['cmd1'],
        routes: ['r1', 'r2', 'r3'],
        configKeys: [],
        templates: ['t1'],
        translationKeys: ['k1', 'k2'],
    );

    $stream = fopen('php://memory', 'w+');
    $output = new Output($stream);
    $command = new RebuildIndexCommand($cache);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $printed = stream_get_contents($stream);
    fclose($stream);

    expect($printed)
        ->toContain('3 modules')
        ->toContain('1 observers')
        ->toContain('2 plugins')
        ->toContain('0 preferences')
        ->toContain('1 commands')
        ->toContain('3 routes')
        ->toContain('0 config keys')
        ->toContain('1 templates')
        ->toContain('2 translation keys');
});

it('prints the cache file path on success', function (): void {
    $cache = makeStubCache();

    $stream = fopen('php://memory', 'w+');
    $output = new Output($stream);
    $command = new RebuildIndexCommand($cache);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $printed = stream_get_contents($stream);
    fclose($stream);

    expect($printed)->toContain('.marko/index.cache');
});

it('exits with non-zero and a helpful message on failure', function (): void {
    $cache = makeThrowingCache();

    $outStream = fopen('php://memory', 'w+');
    $errStream = fopen('php://memory', 'w+');
    $output = new Output($outStream);
    $errorOutput = new Output($errStream);
    $command = new RebuildIndexCommand($cache, $errorOutput);

    $result = $command->execute(new Input([]), $output);

    rewind($errStream);
    $errPrinted = stream_get_contents($errStream);
    fclose($outStream);
    fclose($errStream);

    expect($result)->toBe(1)
        ->and($errPrinted)->toContain('Cannot write to index cache');
});

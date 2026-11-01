<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Filesystem\Config\FilesystemConfig;
use Marko\Filesystem\Contracts\FilesystemDriverFactoryInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Contracts\FilesystemManagerInterface;
use Marko\Filesystem\DefaultFilesystem;
use Marko\Filesystem\Discovery\DriverRegistry;
use Marko\Filesystem\Manager\FilesystemManager;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeFilesystem;

function filesystemManagerUnderTest(): FilesystemManager
{
    $config = new FilesystemConfig(new FakeConfigRepository([
        'filesystem.default' => 'local',
        'filesystem.disks' => [
            'local' => ['driver' => 'memory'],
            's3' => ['driver' => 'memory'],
        ],
    ]));

    $registry = new readonly class () extends DriverRegistry
    {
        public function __construct() {}

        public function get(
            string $name,
        ): string {
            return FilesystemDriverFactoryInterface::class;
        }
    };

    $container = new class () implements ContainerInterface
    {
        public function get(
            string $id,
        ): FilesystemDriverFactoryInterface {
            return new class () implements FilesystemDriverFactoryInterface
            {
                public function create(
                    array $config,
                ): FilesystemInterface {
                    return new FakeFilesystem(clock: new FakeClock(), public: false);
                }
            };
        }

        public function has(
            string $id,
        ): bool {
            return true;
        }

        public function singleton(
            string $id,
        ): void {}

        public function instance(
            string $id,
            object $instance,
        ): void {}

        public function call(
            Closure $callable,
        ): mixed {
            return $callable();
        }

        public function resolvedInstances(
            ?string $interface = null,
        ): array {
            return [];
        }
    };

    return new FilesystemManager($config, $registry, $container);
}

it('implements FilesystemManagerInterface', function (): void {
    expect(filesystemManagerUnderTest())->toBeInstanceOf(FilesystemManagerInterface::class);
});

it('binds FilesystemManagerInterface to FilesystemManager and keeps the concrete binding', function (): void {
    $module = require dirname(__DIR__, 3) . '/module.php';

    expect($module['bindings'][FilesystemManagerInterface::class])->toBe(FilesystemManager::class)
        ->and($module['bindings'][FilesystemInterface::class])->toBe(DefaultFilesystem::class);
});

it('caches each disk under its name', function (): void {
    $manager = filesystemManagerUnderTest();

    expect($manager->disk('s3'))->toBe($manager->disk('s3'))
        ->and($manager->disk('s3'))->not->toBe($manager->disk('local'));
});

it('caches the default disk under its real name', function (): void {
    $manager = filesystemManagerUnderTest();

    expect($manager->disk())->toBe($manager->disk('local'));
});

it('replaces a named disk with useDisk and leaves the other disks alone', function (): void {
    $manager = filesystemManagerUnderTest();
    $replacement = new FakeFilesystem(clock: new FakeClock(), public: true);
    $local = $manager->disk('local');

    $manager->useDisk('s3', $replacement);

    expect($manager->disk('s3'))->toBe($replacement)
        ->and($manager->disk('local'))->toBe($local);
});

it('replaces a disk that was already resolved', function (): void {
    $manager = filesystemManagerUnderTest();
    $original = $manager->disk('s3');
    $replacement = new FakeFilesystem(clock: new FakeClock(), public: true);

    $manager->useDisk('s3', $replacement);

    expect($manager->disk('s3'))->toBe($replacement)
        ->and($manager->disk('s3'))->not->toBe($original);
});

it('replaces the default disk entry when useDisk receives null', function (): void {
    $manager = filesystemManagerUnderTest();
    $replacement = new FakeFilesystem(clock: new FakeClock(), public: true);

    $manager->useDisk(null, $replacement);

    expect($manager->disk())->toBe($replacement)
        ->and($manager->disk('local'))->toBe($replacement);
});

it('replaces the default disk when useDisk receives the default disk name', function (): void {
    $manager = filesystemManagerUnderTest();
    $replacement = new FakeFilesystem(clock: new FakeClock(), public: true);

    $manager->useDisk('local', $replacement);

    expect($manager->disk())->toBe($replacement);
});

it('routes DefaultFilesystem through a replaced default disk', function (): void {
    $manager = filesystemManagerUnderTest();
    $replacement = new FakeFilesystem(clock: new FakeClock(), public: true);
    $manager->useDisk(null, $replacement);

    new DefaultFilesystem($manager)->write('avatars/1.png', 'png-bytes');

    expect($replacement->read('avatars/1.png'))->toBe('png-bytes');
});

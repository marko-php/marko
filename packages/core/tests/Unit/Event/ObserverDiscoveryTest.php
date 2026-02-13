<?php

declare(strict_types=1);

use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Event\Event;
use Marko\Core\Event\ObserverDiscovery;
use Marko\Core\Exceptions\EventException;
use Marko\Core\Module\ModuleManifest;

// Create an event class for testing (needs to exist for attribute to reference)
class DiscoveryTestEvent extends Event {}

it('discovers observer classes in module src directories', function (): void {
    // Create a temp directory structure with observer files
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    // Create an observer class file with Observer attribute
    $observerCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestModule;

use Marko\Core\Attributes\Observer;
use DiscoveryTestEvent;

#[Observer(event: DiscoveryTestEvent::class)]
class TestObserver
{
    public function handle(DiscoveryTestEvent $event): void {}
}
PHP;
    file_put_contents($tempDir . '/src/TestObserver.php', $observerCode);

    // Create module manifest
    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new ObserverDiscovery(new ClassFileParser());
    $observers = $discovery->discover([$manifest]);

    expect($observers)->toHaveCount(1)
        ->and($observers[0]->observerClass)->toBe('TestModule\\TestObserver');

    // Cleanup
    unlink($tempDir . '/src/TestObserver.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('extracts event class/name from Observer attribute', function (): void {
    // Create a temp directory structure with observer files
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    // Create an observer class file
    $observerCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestModule2;

use Marko\Core\Attributes\Observer;
use DiscoveryTestEvent;

#[Observer(event: DiscoveryTestEvent::class, priority: 50)]
class EventExtractObserver
{
    public function handle(DiscoveryTestEvent $event): void {}
}
PHP;
    file_put_contents($tempDir . '/src/EventExtractObserver.php', $observerCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new ObserverDiscovery(new ClassFileParser());
    $observers = $discovery->discover([$manifest]);

    expect($observers)->toHaveCount(1)
        ->and($observers[0]->eventClass)->toBe(DiscoveryTestEvent::class)
        ->and($observers[0]->priority)->toBe(50);

    // Cleanup
    unlink($tempDir . '/src/EventExtractObserver.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('throws exception when observer missing handle method', function (): void {
    // Create a temp directory structure with observer files
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    // Create an observer class file without handle method
    $observerCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestModule3;

use Marko\Core\Attributes\Observer;
use DiscoveryTestEvent;

#[Observer(event: DiscoveryTestEvent::class)]
class MissingHandleObserver
{
    // Missing handle method!
}
PHP;
    file_put_contents($tempDir . '/src/MissingHandleObserver.php', $observerCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new ObserverDiscovery(new ClassFileParser());

    expect(fn () => $discovery->discover([$manifest]))
        ->toThrow(EventException::class, 'must have a handle method');

    // Cleanup
    unlink($tempDir . '/src/MissingHandleObserver.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('skips files that reference missing Marko package classes', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    // Create an observer file that implements a non-existent Marko interface
    $observerCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestModuleMissing;

use Marko\Core\Attributes\Observer;
use DiscoveryTestEvent;

#[Observer(event: DiscoveryTestEvent::class)]
class MissingDependencyObserver implements \Marko\NonExistentPackage\Contracts\SomeInterface
{
    public function handle(DiscoveryTestEvent $event): void {}
}
PHP;
    file_put_contents($tempDir . '/src/MissingDependencyObserver.php', $observerCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new ObserverDiscovery(new ClassFileParser());
    $observers = $discovery->discover([$manifest]);

    // File is silently skipped — no exception, no observers from it
    expect($observers)->toBeEmpty();

    // Cleanup
    unlink($tempDir . '/src/MissingDependencyObserver.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('throws Error when file references missing non-Marko class', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    // Create an observer file that references a non-Marko missing class
    $observerCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestModuleNonMarko;

use Marko\Core\Attributes\Observer;
use DiscoveryTestEvent;

#[Observer(event: DiscoveryTestEvent::class)]
class BadDependencyObserver implements \SomeVendor\Missing\SomeInterface
{
    public function handle(DiscoveryTestEvent $event): void {}
}
PHP;
    file_put_contents($tempDir . '/src/BadDependencyObserver.php', $observerCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new ObserverDiscovery(new ClassFileParser());

    try {
        expect(fn () => $discovery->discover([$manifest]))
            ->toThrow(Error::class, 'not found');
    } finally {
        unlink($tempDir . '/src/BadDependencyObserver.php');
        rmdir($tempDir . '/src');
        rmdir($tempDir);
    }
});

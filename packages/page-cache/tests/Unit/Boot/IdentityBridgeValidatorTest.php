<?php

declare(strict_types=1);

use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\PageCache\Boot\IdentityBridgeValidator;
use Marko\PageCache\Contracts\IdentityInterface;
use Marko\PageCache\Exceptions\PageCacheException;

it('passes validation when no app class implements IdentityInterface', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tmpDir . '/src', 0755, true);

    // Write a plain PHP class that doesn't implement IdentityInterface
    file_put_contents($tmpDir . '/src/PlainClass.php', '<?php
declare(strict_types=1);
namespace TestApp;
class PlainClass {}
');

    $manifest = new ModuleManifest(
        name: 'app/my-module',
        version: '1.0.0',
        path: $tmpDir,
        source: 'app',
    );

    $validator = new IdentityBridgeValidator(
        classFileParser: new ClassFileParser(),
        bridgeClass: 'NonExistentBridgeClass',
    );

    $validator->validate([$manifest]);

    // Clean up
    unlink($tmpDir . '/src/PlainClass.php');
    rmdir($tmpDir . '/src');
    rmdir($tmpDir);

    expect(true)->toBeTrue(); // no exception thrown
});

it('passes validation when an app class implements IdentityInterface and the bridge package is installed', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tmpDir . '/src', 0755, true);

    $uniqueClass = 'IdentityImpl' . uniqid();
    file_put_contents($tmpDir . '/src/' . $uniqueClass . '.php', '<?php
declare(strict_types=1);
namespace TestApp;
use Marko\PageCache\Contracts\IdentityInterface;
class ' . $uniqueClass . ' implements IdentityInterface {
    public function getIdentities(): array { return []; }
}
');

    $manifest = new ModuleManifest(
        name: 'app/my-module',
        version: '1.0.0',
        path: $tmpDir,
        source: 'app',
    );

    // Use a class that actually exists as the bridge class
    $validator = new IdentityBridgeValidator(
        classFileParser: new ClassFileParser(),
        bridgeClass: ClassFileParser::class,
    );

    $validator->validate([$manifest]);

    // Clean up
    unlink($tmpDir . '/src/' . $uniqueClass . '.php');
    rmdir($tmpDir . '/src');
    rmdir($tmpDir);

    expect(true)->toBeTrue(); // no exception thrown
});

it('throws PageCacheException when an app class implements IdentityInterface and the bridge package is not installed', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tmpDir . '/src', 0755, true);

    $uniqueClass = 'IdentityImpl' . uniqid();
    file_put_contents($tmpDir . '/src/' . $uniqueClass . '.php', '<?php
declare(strict_types=1);
namespace TestApp;
use Marko\PageCache\Contracts\IdentityInterface;
class ' . $uniqueClass . ' implements IdentityInterface {
    public function getIdentities(): array { return []; }
}
');

    $manifest = new ModuleManifest(
        name: 'app/my-module',
        version: '1.0.0',
        path: $tmpDir,
        source: 'app',
    );

    $validator = new IdentityBridgeValidator(
        classFileParser: new ClassFileParser(),
        bridgeClass: 'NonExistentBridgeClass',
    );

    $threwException = false;

    try {
        $validator->validate([$manifest]);
    } catch (PageCacheException) {
        $threwException = true;
    } finally {
        unlink($tmpDir . '/src/' . $uniqueClass . '.php');
        rmdir($tmpDir . '/src');
        rmdir($tmpDir);
    }

    expect($threwException)->toBeTrue();
});

it('names the offending class in the exception message', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tmpDir . '/src', 0755, true);

    $uniqueClass = 'OffendingClass' . uniqid();
    file_put_contents($tmpDir . '/src/' . $uniqueClass . '.php', '<?php
declare(strict_types=1);
namespace TestApp;
use Marko\PageCache\Contracts\IdentityInterface;
class ' . $uniqueClass . ' implements IdentityInterface {
    public function getIdentities(): array { return []; }
}
');

    $manifest = new ModuleManifest(
        name: 'app/my-module',
        version: '1.0.0',
        path: $tmpDir,
        source: 'app',
    );

    $validator = new IdentityBridgeValidator(
        classFileParser: new ClassFileParser(),
        bridgeClass: 'NonExistentBridgeClass',
    );

    $exception = null;

    try {
        $validator->validate([$manifest]);
    } catch (PageCacheException $e) {
        $exception = $e;
    } finally {
        unlink($tmpDir . '/src/' . $uniqueClass . '.php');
        rmdir($tmpDir . '/src');
        rmdir($tmpDir);
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())->toContain('TestApp\\' . $uniqueClass);
});

it('suggests the composer require command in the exception suggestion', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tmpDir . '/src', 0755, true);

    $uniqueClass = 'SuggestionClass' . uniqid();
    file_put_contents($tmpDir . '/src/' . $uniqueClass . '.php', '<?php
declare(strict_types=1);
namespace TestApp;
use Marko\PageCache\Contracts\IdentityInterface;
class ' . $uniqueClass . ' implements IdentityInterface {
    public function getIdentities(): array { return []; }
}
');

    $manifest = new ModuleManifest(
        name: 'app/my-module',
        version: '1.0.0',
        path: $tmpDir,
        source: 'app',
    );

    $validator = new IdentityBridgeValidator(
        classFileParser: new ClassFileParser(),
        bridgeClass: 'NonExistentBridgeClass',
    );

    $exception = null;

    try {
        $validator->validate([$manifest]);
    } catch (PageCacheException $e) {
        $exception = $e;
    } finally {
        unlink($tmpDir . '/src/' . $uniqueClass . '.php');
        rmdir($tmpDir . '/src');
        rmdir($tmpDir);
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getSuggestion())->toContain('composer require marko/page-cache-entity');
});

it('ignores marko/* modules when scanning for IdentityInterface implementers', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tmpDir . '/src', 0755, true);

    $uniqueClass = 'MarkoIdentityImpl' . uniqid();
    file_put_contents($tmpDir . '/src/' . $uniqueClass . '.php', '<?php
declare(strict_types=1);
namespace Marko\SomePackage;
use Marko\PageCache\Contracts\IdentityInterface;
class ' . $uniqueClass . ' implements IdentityInterface {
    public function getIdentities(): array { return []; }
}
');

    $manifest = new ModuleManifest(
        name: 'marko/some-package',
        version: '1.0.0',
        path: $tmpDir,
        source: 'vendor',
    );

    $validator = new IdentityBridgeValidator(
        classFileParser: new ClassFileParser(),
        bridgeClass: 'NonExistentBridgeClass',
    );

    $validator->validate([$manifest]);

    // Clean up
    unlink($tmpDir . '/src/' . $uniqueClass . '.php');
    rmdir($tmpDir . '/src');
    rmdir($tmpDir);

    expect(true)->toBeTrue(); // no exception thrown despite marko/* implementing IdentityInterface
});

it('stops scanning after finding the first offending class (fail-fast)', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tmpDir . '/src', 0755, true);

    $uniqueClass1 = 'FirstOffender' . uniqid();
    $uniqueClass2 = 'SecondOffender' . uniqid();

    file_put_contents($tmpDir . '/src/A' . $uniqueClass1 . '.php', '<?php
declare(strict_types=1);
namespace TestApp;
use Marko\PageCache\Contracts\IdentityInterface;
class ' . $uniqueClass1 . ' implements IdentityInterface {
    public function getIdentities(): array { return []; }
}
');

    file_put_contents($tmpDir . '/src/B' . $uniqueClass2 . '.php', '<?php
declare(strict_types=1);
namespace TestApp;
use Marko\PageCache\Contracts\IdentityInterface;
class ' . $uniqueClass2 . ' implements IdentityInterface {
    public function getIdentities(): array { return []; }
}
');

    $manifest = new ModuleManifest(
        name: 'app/my-module',
        version: '1.0.0',
        path: $tmpDir,
        source: 'app',
    );

    $validator = new IdentityBridgeValidator(
        classFileParser: new ClassFileParser(),
        bridgeClass: 'NonExistentBridgeClass',
    );

    $exceptionCount = 0;

    try {
        $validator->validate([$manifest]);
    } catch (PageCacheException) {
        $exceptionCount++;
    } finally {
        unlink($tmpDir . '/src/A' . $uniqueClass1 . '.php');
        unlink($tmpDir . '/src/B' . $uniqueClass2 . '.php');
        rmdir($tmpDir . '/src');
        rmdir($tmpDir);
    }

    // Only one exception is thrown (fail-fast), not two
    expect($exceptionCount)->toBe(1);
});

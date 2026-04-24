<?php

declare(strict_types=1);

use Marko\CodeIndexer\Contract\AttributeParserInterface;
use Marko\CodeIndexer\Contract\ConfigScannerInterface;
use Marko\CodeIndexer\Contract\IndexCacheInterface;
use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\CodeIndexer\Contract\TemplateScannerInterface;
use Marko\CodeIndexer\Contract\TranslationScannerInterface;
use Marko\CodeIndexer\ValueObject\CommandEntry;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\ObserverEntry;
use Marko\CodeIndexer\ValueObject\PluginEntry;
use Marko\CodeIndexer\ValueObject\PreferenceEntry;
use Marko\CodeIndexer\ValueObject\RouteEntry;
use Marko\CodeIndexer\ValueObject\TemplateEntry;
use Marko\CodeIndexer\ValueObject\TranslationEntry;

it('has composer.json with name marko/codeindexer and PSR-4 namespace Marko\\CodeIndexer\\', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect(file_exists($composerPath))->toBeTrue()
        ->and($composer['name'])->toBe('marko/codeindexer')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\CodeIndexer\\')
        ->and($composer['autoload']['psr-4']['Marko\\CodeIndexer\\'])->toBe('src/');
});

it('has module.php with empty bindings and singletons arrays', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBe([])
        ->and($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toBe([]);
});

it('has src/ tests/Unit tests/Feature directories', function (): void {
    $base = dirname(__DIR__, 2);

    expect(is_dir($base . '/src'))->toBeTrue()
        ->and(is_dir($base . '/tests/Unit'))->toBeTrue()
        ->and(is_dir($base . '/tests/Feature'))->toBeTrue();
});

it('has tests/Pest.php that configures Pest with TestCase', function (): void {
    $pestPath = dirname(__DIR__, 2) . '/tests/Pest.php';

    expect(file_exists($pestPath))->toBeTrue();

    $contents = file_get_contents($pestPath);

    expect($contents)->toContain('declare(strict_types=1)');
});

it('requires PHP ^8.5, marko/core, and nikic/php-parser ^5.4', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('nikic/php-parser')
        ->and($composer['require']['nikic/php-parser'])->toBe('^5.4');
});

it('autoloads cleanly with composer dump-autoload', function (): void {
    $base = dirname(__DIR__, 2);
    $composerPath = $base . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\CodeIndexer\\')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\CodeIndexer\\Tests\\');
});

it('defines IndexCacheInterface, ModuleWalkerInterface, AttributeParserInterface, ConfigScannerInterface, TemplateScannerInterface, TranslationScannerInterface contracts', function (): void {
    expect(interface_exists(IndexCacheInterface::class))->toBeTrue()
        ->and(interface_exists(ModuleWalkerInterface::class))->toBeTrue()
        ->and(interface_exists(AttributeParserInterface::class))->toBeTrue()
        ->and(interface_exists(ConfigScannerInterface::class))->toBeTrue()
        ->and(interface_exists(TemplateScannerInterface::class))->toBeTrue()
        ->and(interface_exists(TranslationScannerInterface::class))->toBeTrue();
});

it('defines readonly value object types: ModuleInfo, ObserverEntry, PluginEntry, PreferenceEntry, CommandEntry, RouteEntry, ConfigKeyEntry, TemplateEntry, TranslationEntry', function (): void {
    $valueObjects = [
        ModuleInfo::class,
        ObserverEntry::class,
        PluginEntry::class,
        PreferenceEntry::class,
        CommandEntry::class,
        RouteEntry::class,
        ConfigKeyEntry::class,
        TemplateEntry::class,
        TranslationEntry::class,
    ];

    foreach ($valueObjects as $class) {
        $reflection = new ReflectionClass($class);
        expect($reflection->isReadOnly())->toBeTrue("Expected $class to be readonly");
    }

    $moduleInfo = new ModuleInfo('test', '/path', 'Ns');
    expect($moduleInfo->name)->toBe('test')
        ->and($moduleInfo->path)->toBe('/path')
        ->and($moduleInfo->namespace)->toBe('Ns');

    $observerEntry = new ObserverEntry('Cls', 'evt', 'mth', 10);
    expect($observerEntry->class)->toBe('Cls')
        ->and($observerEntry->event)->toBe('evt')
        ->and($observerEntry->method)->toBe('mth')
        ->and($observerEntry->sortOrder)->toBe(10);

    $pluginEntry = new PluginEntry('Cls', 'tgt', 'mth', 'before', 5);
    expect($pluginEntry->class)->toBe('Cls')
        ->and($pluginEntry->target)->toBe('tgt')
        ->and($pluginEntry->method)->toBe('mth')
        ->and($pluginEntry->type)->toBe('before')
        ->and($pluginEntry->sortOrder)->toBe(5);

    $preferenceEntry = new PreferenceEntry('Iface', 'Impl', 'mod');
    expect($preferenceEntry->interface)->toBe('Iface')
        ->and($preferenceEntry->implementation)->toBe('Impl')
        ->and($preferenceEntry->module)->toBe('mod');

    $commandEntry = new CommandEntry('cmd:name', 'CmdCls', 'desc');
    expect($commandEntry->name)->toBe('cmd:name')
        ->and($commandEntry->class)->toBe('CmdCls')
        ->and($commandEntry->description)->toBe('desc');

    $routeEntry = new RouteEntry('GET', '/path', 'Ctrl', 'index');
    expect($routeEntry->method)->toBe('GET')
        ->and($routeEntry->path)->toBe('/path')
        ->and($routeEntry->class)->toBe('Ctrl')
        ->and($routeEntry->action)->toBe('index');

    $configKeyEntry = new ConfigKeyEntry('app.name', 'string', 'Marko', 'core');
    expect($configKeyEntry->key)->toBe('app.name')
        ->and($configKeyEntry->type)->toBe('string')
        ->and($configKeyEntry->defaultValue)->toBe('Marko')
        ->and($configKeyEntry->module)->toBe('core');

    $templateEntry = new TemplateEntry('mod', 'tmpl-1', '/tmpl.latte', 'latte');
    expect($templateEntry->moduleName)->toBe('mod')
        ->and($templateEntry->templateName)->toBe('tmpl-1')
        ->and($templateEntry->absolutePath)->toBe('/tmpl.latte')
        ->and($templateEntry->extension)->toBe('latte');

    $translationEntry = new TranslationEntry(
        key: 'messages.hello',
        group: 'messages',
        locale: 'en',
        namespace: 'mod',
        file: '/path/to/messages.php',
        line: 5,
        module: 'mod',
    );
    expect($translationEntry->key)->toBe('messages.hello')
        ->and($translationEntry->group)->toBe('messages')
        ->and($translationEntry->locale)->toBe('en')
        ->and($translationEntry->namespace)->toBe('mod')
        ->and($translationEntry->module)->toBe('mod');
});

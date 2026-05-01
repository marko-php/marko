<?php

declare(strict_types=1);

use Marko\CodeIndexer\Attributes\AttributeParser;
use Marko\CodeIndexer\ValueObject\ModuleInfo;

$fixtureBase = dirname(__DIR__, 2) . '/Fixtures/AttributeFixtures';

$fixtureModule = new ModuleInfo(
    name: 'fixture/attributefixtures',
    path: $fixtureBase,
    namespace: 'Fixture\AttributeFixtures',
);

it('parses class-level Observer attributes with event class reference', function () use ($fixtureModule): void {
    $parser = new AttributeParser();
    $observers = $parser->observers($fixtureModule);

    expect($observers)->toHaveCount(1);

    $entry = $observers[0];
    expect($entry->class)->toEndWith('UserCreatedObserver')
        ->and($entry->event)->toBe('Fixture\AttributeFixtures\Events\UserCreated')
        ->and($entry->sortOrder)->toBe(10);
});

it('records syntax-error files as diagnostics without aborting the scan', function () use ($fixtureBase): void {
    // Create a module that includes a broken file alongside valid ones
    $tmpDir = sys_get_temp_dir() . '/marko-diag-test-' . uniqid();
    mkdir($tmpDir . '/src', 0777, true);

    file_put_contents($tmpDir . '/src/GoodObserver.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace Diag\Module;
        use Marko\Core\Attributes\Observer;
        #[Observer(event: 'Diag\Module\Events\SomeEvent', priority: 5)]
        class GoodObserver {}
        PHP);

    file_put_contents($tmpDir . '/src/BrokenFile.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace Diag\Module;
        class Broken { public function oops(: void {} }
        PHP);

    $module = new ModuleInfo(
        name: 'diag/module',
        path: $tmpDir,
        namespace: 'Diag\Module',
    );

    $parser = new AttributeParser();
    $observers = $parser->observers($module);

    // Valid file was still parsed
    expect($observers)->toHaveCount(1)
        ->and($observers[0]->class)->toEndWith('GoodObserver');

    // Broken file recorded in diagnostics
    $diagnostics = $parser->diagnostics();
    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]['file'])->toContain('BrokenFile.php')
        ->and($diagnostics[0]['error'])->not->toBeEmpty();
});

it('does not require target classes to be autoloadable (pure AST traversal, no class loading)', function () use ($fixtureBase): void {
    // Create a temporary module pointing to a file that references non-autoloadable classes
    $tmpDir = sys_get_temp_dir() . '/marko-ast-test-' . uniqid();
    mkdir($tmpDir . '/src', 0777, true);

    file_put_contents($tmpDir . '/src/NonAutoloadableObserver.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace NonAutoloadable\Module;
        use NonAutoloadable\Completely\FakeAttribute\Observer;
        use NonAutoloadable\Completely\FakeEvent\SomethingHappened;
        #[Observer(event: 'NonAutoloadable\Completely\FakeEvent\SomethingHappened', priority: 0)]
        class NonAutoloadableObserver {}
        PHP);

    $module = new ModuleInfo(
        name: 'non/autoloadable',
        path: $tmpDir,
        namespace: 'NonAutoloadable\Module',
    );

    // Parser should NOT throw — it uses AST, not class_exists / reflection
    $parser = new AttributeParser();

    // The attribute FQN won't match Marko\Core\Attributes\Observer, so result is empty —
    // but crucially, no exception is thrown even though the classes don't exist.
    expect(fn () => $parser->observers($module))->not->toThrow(Throwable::class);
});

it('resolves short attribute names via file use statements to fully qualified class names', function () use ($fixtureBase, $fixtureModule): void {
    // The fixture files use short names like `Observer`, `Plugin`, etc. via `use` statements.
    // The NameResolver must expand them to FQNs for matching to work.
    $parser = new AttributeParser();

    // If NameResolver was NOT used, none of these would return results
    // because the attribute names would remain unresolved short names.
    expect($parser->observers($fixtureModule))->not->toBeEmpty()
        ->and($parser->plugins($fixtureModule))->not->toBeEmpty()
        ->and($parser->preferences($fixtureModule))->not->toBeEmpty()
        ->and($parser->commands($fixtureModule))->not->toBeEmpty()
        ->and($parser->routes($fixtureModule))->not->toBeEmpty();
});

it('returns empty entries for modules with no attributes', function (): void {
    $emptyModule = new ModuleInfo(
        name: 'empty/module',
        path: sys_get_temp_dir() . '/marko-test-empty-' . uniqid(),
        namespace: 'Empty\Module',
    );
    $parser = new AttributeParser();

    expect($parser->observers($emptyModule))->toBeEmpty()
        ->and($parser->plugins($emptyModule))->toBeEmpty()
        ->and($parser->preferences($emptyModule))->toBeEmpty()
        ->and($parser->commands($emptyModule))->toBeEmpty()
        ->and($parser->routes($emptyModule))->toBeEmpty();
});

it('skips classes outside the specified module namespace', function () use ($fixtureBase): void {
    $wrongModule = new ModuleInfo(
        name: 'other/module',
        path: $fixtureBase,
        namespace: 'Other\Namespace',
    );
    $parser = new AttributeParser();

    expect($parser->observers($wrongModule))->toBeEmpty()
        ->and($parser->plugins($wrongModule))->toBeEmpty()
        ->and($parser->preferences($wrongModule))->toBeEmpty()
        ->and($parser->commands($wrongModule))->toBeEmpty()
        ->and($parser->routes($wrongModule))->toBeEmpty();
});

it('parses DisableRoute attributes on overridden methods', function () use ($fixtureModule): void {
    $parser = new AttributeParser();
    $routes = $parser->routes($fixtureModule);

    $disabled = array_values(array_filter($routes, fn ($e) => $e->method === 'DISABLED'));

    expect($disabled)->toHaveCount(1);

    $entry = $disabled[0];
    expect($entry->action)->toBe('disabled')
        ->and($entry->class)->toEndWith('PostController');
});

it('parses route attributes Get/Post/Put/Patch/Delete with path and middleware', function () use ($fixtureModule): void {
    $parser = new AttributeParser();
    $routes = $parser->routes($fixtureModule);

    $active = array_values(array_filter($routes, fn ($e) => $e->method !== 'DISABLED'));

    expect($active)->toHaveCount(6);

    $byAction = [];
    foreach ($active as $entry) {
        $byAction[$entry->action] = $entry;
    }

    expect($byAction['index']->method)->toBe('GET')
        ->and($byAction['index']->path)->toBe('/posts')
        ->and($byAction['show']->method)->toBe('GET')
        ->and($byAction['show']->path)->toBe('/posts/{id}')
        ->and($byAction['store']->method)->toBe('POST')
        ->and($byAction['update']->method)->toBe('PUT')
        ->and($byAction['patch']->method)->toBe('PATCH')
        ->and($byAction['destroy']->method)->toBe('DELETE');
});

it('parses Command attributes from class-level declarations', function () use ($fixtureModule): void {
    $parser = new AttributeParser();
    $commands = $parser->commands($fixtureModule);

    expect($commands)->toHaveCount(1);

    $entry = $commands[0];
    expect($entry->name)->toBe('fixture:install')
        ->and($entry->class)->toEndWith('InstallCommand')
        ->and($entry->description)->toBe('Install fixtures');
});

it('parses Preference attributes and maps replacement class to replaced class', function () use ($fixtureModule): void {
    $parser = new AttributeParser();
    $preferences = $parser->preferences($fixtureModule);

    expect($preferences)->toHaveCount(1);

    $entry = $preferences[0];
    expect($entry->implementation)->toEndWith('CustomLoggerPreference')
        ->and($entry->interface)->toBe('Fixture\AttributeFixtures\Contracts\LoggerInterface')
        ->and($entry->module)->toBe('fixture/attributefixtures');
});

it('parses Plugin attributes and associates methods to target class', function () use ($fixtureModule): void {
    $parser = new AttributeParser();
    $plugins = $parser->plugins($fixtureModule);

    expect($plugins)->toHaveCount(2);

    foreach ($plugins as $entry) {
        expect($entry->class)->toEndWith('PaymentPlugin')
            ->and($entry->target)->toBe('Fixture\AttributeFixtures\Services\PaymentService');
    }
});

it('parses method-level After attributes with sortOrder', function () use ($fixtureModule): void {
    $parser = new AttributeParser();
    $plugins = $parser->plugins($fixtureModule);

    $after = array_values(array_filter($plugins, fn ($e) => $e->type === 'after'));

    expect($after)->toHaveCount(1);

    $entry = $after[0];
    expect($entry->class)->toEndWith('PaymentPlugin')
        ->and($entry->target)->toBe('Fixture\AttributeFixtures\Services\PaymentService')
        ->and($entry->method)->toBe('process')
        ->and($entry->type)->toBe('after')
        ->and($entry->sortOrder)->toBe(20);
});

it('parses method-level Before attributes with sortOrder and target method', function () use ($fixtureModule): void {
    $parser = new AttributeParser();
    $plugins = $parser->plugins($fixtureModule);

    $before = array_values(array_filter($plugins, fn ($e) => $e->type === 'before'));

    expect($before)->toHaveCount(1);

    $entry = $before[0];
    expect($entry->class)->toEndWith('PaymentPlugin')
        ->and($entry->target)->toBe('Fixture\AttributeFixtures\Services\PaymentService')
        ->and($entry->method)->toBe('process')
        ->and($entry->type)->toBe('before')
        ->and($entry->sortOrder)->toBe(10);
});

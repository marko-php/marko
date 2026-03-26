<?php

declare(strict_types=1);

use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Exceptions\PluginException;

it('throws BindingException when no implementation exists for interface', function (): void {
    expect(class_exists(MarkoException::class))->toBeTrue()
        ->and(class_exists(BindingException::class))->toBeTrue();

    $interface = 'App\Contracts\UserRepositoryInterface';
    $exception = BindingException::noImplementation($interface);

    expect($exception)
        ->toBeInstanceOf(BindingException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())
        ->toContain('No implementation bound')
        ->and($exception->getContext())
        ->toContain($interface);
});

it('throws BindingConflictException when multiple modules bind same interface', function (): void {
    expect(class_exists(BindingConflictException::class))->toBeTrue();

    $interface = 'App\Contracts\UserRepositoryInterface';
    $modules = ['Vendor_ModuleA', 'Vendor_ModuleB'];
    $exception = BindingConflictException::multipleBindings($interface, $modules);

    expect($exception)
        ->toBeInstanceOf(BindingConflictException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())
        ->toContain($interface)
        ->toContain('Vendor_ModuleA')
        ->toContain('Vendor_ModuleB');
});

it('throws ModuleException when module manifest is invalid', function (): void {
    expect(class_exists(ModuleException::class))->toBeTrue();

    $moduleName = 'Vendor_InvalidModule';
    $reason = 'Missing required key: name';
    $exception = ModuleException::invalidManifest($moduleName, $reason);

    expect($exception)
        ->toBeInstanceOf(ModuleException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())
        ->toContain($moduleName)
        ->toContain($reason);
});

it('throws CircularDependencyException when modules have circular dependencies', function (): void {
    expect(class_exists(CircularDependencyException::class))->toBeTrue();

    $chain = ['ModuleA', 'ModuleB', 'ModuleC', 'ModuleA'];
    $exception = CircularDependencyException::detected($chain);

    expect($exception)
        ->toBeInstanceOf(CircularDependencyException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())
        ->toContain('ModuleA')
        ->toContain('ModuleB')
        ->toContain('ModuleC')
        ->toContain('Circular');
});

it('throws PluginException when plugin configuration is invalid', function (): void {
    expect(class_exists(PluginException::class))->toBeTrue();

    $pluginClass = 'App\Plugins\UserPlugin';
    $reason = 'Plugin method does not match target signature';
    $exception = PluginException::invalidConfiguration($pluginClass, $reason);

    expect($exception)
        ->toBeInstanceOf(PluginException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())
        ->toContain($pluginClass)
        ->toContain($reason);
});

it('includes helpful message with what went wrong in all exceptions', function (): void {
    $message = 'Something went wrong';
    $baseException = new MarkoException($message);
    $bindingException = BindingException::noImplementation('SomeInterface');
    $conflictException = BindingConflictException::multipleBindings('SomeInterface', ['A', 'B']);
    $moduleException = ModuleException::invalidManifest('Module', 'reason');
    $circularException = CircularDependencyException::detected(['A', 'B', 'A']);
    $pluginException = PluginException::invalidConfiguration('Plugin', 'reason');

    expect($baseException->getMessage())->toBe($message)
        ->and($bindingException->getMessage())->not->toBeEmpty()
        ->and($bindingException->getContext())->toContain('SomeInterface')
        ->and($conflictException->getMessage())->not->toBeEmpty()->toContain('SomeInterface')
        ->and($moduleException->getMessage())->not->toBeEmpty()->toContain('Module')
        ->and($circularException->getMessage())->not->toBeEmpty()->toContain('A')
        ->and($pluginException->getMessage())->not->toBeEmpty()->toContain('Plugin');
});

it('includes context about where error occurred in all exceptions', function (): void {
    $message = 'Something went wrong';
    $context = 'While resolving dependencies for UserService';
    $baseException = new MarkoException($message, $context);
    $bindingException = BindingException::noImplementation('SomeInterface');
    $conflictException = BindingConflictException::multipleBindings('SomeInterface', ['A', 'B']);
    $moduleException = ModuleException::invalidManifest('TestModule', 'reason');
    $circularException = CircularDependencyException::detected(['A', 'B', 'A']);
    $pluginException = PluginException::invalidConfiguration('TestPlugin', 'reason');

    expect($baseException->getContext())->toBe($context)
        ->and($bindingException->getContext())->not->toBeEmpty()->toContain('SomeInterface')
        ->and($conflictException->getContext())->not->toBeEmpty()
        ->and($moduleException->getContext())->not->toBeEmpty()->toContain('TestModule')
        ->and($circularException->getContext())->not->toBeEmpty()
        ->and($pluginException->getContext())->not->toBeEmpty()->toContain('TestPlugin');
});

it('BindingException noImplementation still works as generic fallback', function (): void {
    $interface = 'App\Contracts\SomeInterface';
    $exception = BindingException::noImplementation($interface);

    expect($exception)
        ->toBeInstanceOf(BindingException::class)
        ->and($exception->getMessage())
        ->toContain('No implementation bound')
        ->and($exception->getContext())
        ->toContain($interface)
        ->and($exception->getSuggestion())
        ->toContain('bind');
});

it('BindingException no longer has discoverDriverPackages or scanForDriverPackages methods', function (): void {
    expect(method_exists(BindingException::class, 'discoverDriverPackages'))->toBeFalse()
        ->and(method_exists(BindingException::class, 'scanForDriverPackages'))->toBeFalse();
});

it('includes suggestion for how to fix in all exceptions', function (): void {
    $message = 'Something went wrong';
    $context = 'While doing something';
    $suggestion = 'Try doing something else';
    $baseException = new MarkoException($message, $context, $suggestion);
    $bindingException = BindingException::noImplementation('SomeInterface');
    $conflictException = BindingConflictException::multipleBindings('SomeInterface', ['A', 'B']);
    $moduleException = ModuleException::invalidManifest('TestModule', 'Missing name');
    $circularException = CircularDependencyException::detected(['A', 'B', 'A']);
    $pluginException = PluginException::invalidConfiguration('TestPlugin', 'reason');

    expect($baseException->getSuggestion())->toBe($suggestion)
        ->and($bindingException->getSuggestion())->not->toBeEmpty()->toContain('bind')
        ->and($conflictException->getSuggestion())->not->toBeEmpty()
        ->and($moduleException->getSuggestion())->not->toBeEmpty()->toContain('module.php')
        ->and($circularException->getSuggestion())->not->toBeEmpty()->toContain('depend')
        ->and($pluginException->getSuggestion())->not->toBeEmpty()->toContain('plugin');
});

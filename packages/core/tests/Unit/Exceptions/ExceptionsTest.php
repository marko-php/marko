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
    expect($baseException->getMessage())->toBe($message);

    $bindingException = BindingException::noImplementation('SomeInterface');
    expect($bindingException->getMessage())->not->toBeEmpty()
        ->and($bindingException->getContext())->toContain('SomeInterface');

    $conflictException = BindingConflictException::multipleBindings('SomeInterface', ['A', 'B']);
    expect($conflictException->getMessage())->not->toBeEmpty()->toContain('SomeInterface');

    $moduleException = ModuleException::invalidManifest('Module', 'reason');
    expect($moduleException->getMessage())->not->toBeEmpty()->toContain('Module');

    $circularException = CircularDependencyException::detected(['A', 'B', 'A']);
    expect($circularException->getMessage())->not->toBeEmpty()->toContain('A');

    $pluginException = PluginException::invalidConfiguration('Plugin', 'reason');
    expect($pluginException->getMessage())->not->toBeEmpty()->toContain('Plugin');
});

it('includes context about where error occurred in all exceptions', function (): void {
    $message = 'Something went wrong';
    $context = 'While resolving dependencies for UserService';
    $baseException = new MarkoException($message, $context);
    expect($baseException->getContext())->toBe($context);

    $bindingException = BindingException::noImplementation('SomeInterface');
    expect($bindingException->getContext())->not->toBeEmpty()->toContain('SomeInterface');

    $conflictException = BindingConflictException::multipleBindings('SomeInterface', ['A', 'B']);
    expect($conflictException->getContext())->not->toBeEmpty();

    $moduleException = ModuleException::invalidManifest('TestModule', 'reason');
    expect($moduleException->getContext())->not->toBeEmpty()->toContain('TestModule');

    $circularException = CircularDependencyException::detected(['A', 'B', 'A']);
    expect($circularException->getContext())->not->toBeEmpty();

    $pluginException = PluginException::invalidConfiguration('TestPlugin', 'reason');
    expect($pluginException->getContext())->not->toBeEmpty()->toContain('TestPlugin');
});

it('includes suggestion for how to fix in all exceptions', function (): void {
    $message = 'Something went wrong';
    $context = 'While doing something';
    $suggestion = 'Try doing something else';
    $baseException = new MarkoException($message, $context, $suggestion);
    expect($baseException->getSuggestion())->toBe($suggestion);

    $bindingException = BindingException::noImplementation('SomeInterface');
    expect($bindingException->getSuggestion())->not->toBeEmpty()->toContain('bind');

    $conflictException = BindingConflictException::multipleBindings('SomeInterface', ['A', 'B']);
    expect($conflictException->getSuggestion())->not->toBeEmpty();

    $moduleException = ModuleException::invalidManifest('TestModule', 'Missing name');
    expect($moduleException->getSuggestion())->not->toBeEmpty()->toContain('module.php');

    $circularException = CircularDependencyException::detected(['A', 'B', 'A']);
    expect($circularException->getSuggestion())->not->toBeEmpty()->toContain('depend');

    $pluginException = PluginException::invalidConfiguration('TestPlugin', 'reason');
    expect($pluginException->getSuggestion())->not->toBeEmpty()->toContain('plugin');
});

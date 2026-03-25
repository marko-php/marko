<?php

declare(strict_types=1);

use Marko\Core\Plugin\PluginDefinition;

it('creates PluginDefinition with target-method-keyed before methods', function (): void {
    $definition = new PluginDefinition(
        pluginClass: 'Acme\Blog\Plugin\UserServicePlugin',
        targetClass: 'Acme\Blog\Services\UserService',
        beforeMethods: ['save' => ['pluginMethod' => 'save', 'sortOrder' => 10]],
    );

    expect($definition->beforeMethods)
        ->toBeArray()
        ->toHaveKey('save')
        ->and($definition->beforeMethods['save']['pluginMethod'])->toBe('save')
        ->and($definition->beforeMethods['save']['sortOrder'])->toBe(10);
});

it('creates PluginDefinition with target-method-keyed after methods', function (): void {
    $definition = new PluginDefinition(
        pluginClass: 'Acme\Blog\Plugin\UserServicePlugin',
        targetClass: 'Acme\Blog\Services\UserService',
        afterMethods: ['save' => ['pluginMethod' => 'auditSave', 'sortOrder' => 5]],
    );

    expect($definition->afterMethods)
        ->toBeArray()
        ->toHaveKey('save')
        ->and($definition->afterMethods['save']['pluginMethod'])->toBe('auditSave')
        ->and($definition->afterMethods['save']['sortOrder'])->toBe(5);
});

it('creates PluginDefinition with empty method arrays by default', function (): void {
    $definition = new PluginDefinition(
        pluginClass: 'Acme\Blog\Plugin\UserServicePlugin',
        targetClass: 'Acme\Blog\Services\UserService',
    );

    expect($definition->beforeMethods)
        ->toBeArray()
        ->toBeEmpty()
        ->and($definition->afterMethods)
        ->toBeArray()
        ->toBeEmpty();
});

it('stores plugin method name separately from target method name', function (): void {
    $definition = new PluginDefinition(
        pluginClass: 'Acme\Blog\Plugin\UserServicePlugin',
        targetClass: 'Acme\Blog\Services\UserService',
        beforeMethods: ['save' => ['pluginMethod' => 'validateBeforeSave', 'sortOrder' => 0]],
    );

    $targetMethod = array_key_first($definition->beforeMethods);
    $pluginMethod = $definition->beforeMethods[$targetMethod]['pluginMethod'];

    expect($targetMethod)->toBe('save')
        ->and($pluginMethod)->toBe('validateBeforeSave')
        ->and($targetMethod)->not->toBe($pluginMethod);
});

<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Core\Container\ContainerInterface;

it('has enabled set to true', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray();
});

it('has bindings array', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toBeArray();
});

it('binds PasswordHasherInterface to BcryptPasswordHasher', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config['bindings'])->toHaveKey(PasswordHasherInterface::class)
        ->and($config['bindings'][PasswordHasherInterface::class])->toBeInstanceOf(Closure::class);
});

it('binds GuardInterface with factory', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config['bindings'])->toHaveKey(GuardInterface::class)
        ->and($config['bindings'][GuardInterface::class])->toBeInstanceOf(Closure::class);
});

it('creates password hasher with config cost', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;
    $binding = $config['bindings'][PasswordHasherInterface::class];

    $authConfig = $this->createMock(AuthConfig::class);
    $authConfig->expects($this->once())
        ->method('bcryptCost')
        ->willReturn(10);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
        ->method('get')
        ->with(AuthConfig::class)
        ->willReturn($authConfig);

    $result = $binding($container);

    expect($result)->toBeInstanceOf(BcryptPasswordHasher::class)
        ->and($result)->toBeInstanceOf(PasswordHasherInterface::class);
});

it('creates guard via AuthManager', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;
    $binding = $config['bindings'][GuardInterface::class];

    $guard = $this->createMock(GuardInterface::class);

    $authManager = $this->createMock(AuthManager::class);
    $authManager->expects($this->once())
        ->method('guard')
        ->willReturn($guard);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
        ->method('get')
        ->with(AuthManager::class)
        ->willReturn($authManager);

    $result = $binding($container);

    expect($result)->toBeInstanceOf(GuardInterface::class);
});

it('registers AuthManager as singleton', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config)->toHaveKey('singletons')
        ->and($config['singletons'])->toContain(AuthManager::class);
});

it('registers GuardInterface as singleton', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config['singletons'])->toContain(GuardInterface::class);
});

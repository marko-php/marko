<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Core\Container\ContainerInterface;
use Marko\Session\Contracts\SessionInterface;

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

it('binds AuthManager with factory', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config['bindings'])->toHaveKey(AuthManager::class)
        ->and($config['bindings'][AuthManager::class])->toBeInstanceOf(Closure::class);
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

it('creates auth manager with container and config', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;
    $binding = $config['bindings'][AuthManager::class];

    $authConfig = $this->createMock(AuthConfig::class);
    $session = $this->createMock(SessionInterface::class);
    $provider = $this->createMock(UserProviderInterface::class);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->exactly(3))
        ->method('get')
        ->willReturnCallback(function (string $id) use ($authConfig, $session, $provider) {
            return match ($id) {
                AuthConfig::class => $authConfig,
                SessionInterface::class => $session,
                UserProviderInterface::class => $provider,
            };
        });

    $result = $binding($container);

    expect($result)->toBeInstanceOf(AuthManager::class);
});

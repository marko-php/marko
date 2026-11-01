<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\AuthenticationTwoFactor\Config\TwoFactorConfig;
use Marko\AuthenticationTwoFactor\Contracts\TotpInterface;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;
use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorException;
use Marko\AuthenticationTwoFactor\Tests\Fixtures\RecordingUserPersister;
use Marko\AuthenticationTwoFactor\Totp\OtphpTotp;
use Marko\AuthenticationTwoFactor\TwoFactorManager;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Psr\Clock\ClockInterface;

it('has a composer.json for marko/authentication-two-factor as a marko module', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    expect($composer['name'])->toBe('marko/authentication-two-factor')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer)->not->toHaveKey('version')
        ->and($composer['require'])->toHaveKeys([
            'marko/authentication',
            'marko/ratelimiter',
            'spomky-labs/otphp',
        ])
        ->and($composer['autoload']['psr-4'])->toBe(['Marko\\AuthenticationTwoFactor\\' => 'src/'])
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('ships config with every key TwoFactorConfig reads', function (): void {
    $defaults = require dirname(__DIR__) . '/config/authentication-two-factor.php';

    expect($defaults)->toHaveKeys(['issuer', 'setup_route', 'recovery_code_count', 'throttle'])
        ->and($defaults['throttle'])->toHaveKeys(['max_attempts', 'decay_seconds']);
});

it('binds TotpInterface and TwoFactorManagerInterface to working implementations', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module['bindings'])->toBe([
        TotpInterface::class => OtphpTotp::class,
        TwoFactorManagerInterface::class => TwoFactorManager::class,
    ]);

    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ClockInterface::class, new FakeClock());
    $container->instance(EventDispatcherInterface::class, new FakeEventDispatcher());
    $container->instance(UserPersisterInterface::class, new RecordingUserPersister());
    // marko/authentication's module builds the real AuthManager; its wiring is tested there
    $container->instance(AuthManager::class, $this->createStub(AuthManager::class));
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'authentication-two-factor.issuer' => 'Acme',
    ]));

    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    expect($container->get(TwoFactorManagerInterface::class))->toBeInstanceOf(TwoFactorManager::class)
        ->and($container->get(TotpInterface::class))->toBeInstanceOf(OtphpTotp::class)
        ->and($container->get(AuthConfig::class))->toBeInstanceOf(AuthConfig::class);
});

describe('TwoFactorConfig', function (): void {
    it('reads every setting', function (): void {
        $config = new TwoFactorConfig(new FakeConfigRepository([
            'authentication-two-factor.issuer' => 'Acme',
            'authentication-two-factor.setup_route' => '/2fa',
            'authentication-two-factor.recovery_code_count' => 10,
            'authentication-two-factor.throttle.max_attempts' => 4,
            'authentication-two-factor.throttle.decay_seconds' => 120,
        ]));

        expect($config->issuer())->toBe('Acme')
            ->and($config->setupRoute())->toBe('/2fa')
            ->and($config->recoveryCodeCount())->toBe(10)
            ->and($config->throttleMaxAttempts())->toBe(4)
            ->and($config->throttleDecaySeconds())->toBe(120);
    });

    it('throws when the issuer is missing or blank', function (?string $issuer): void {
        new TwoFactorConfig(new FakeConfigRepository([
            'authentication-two-factor.issuer' => $issuer,
        ]))->issuer();
    })->with([null, '', '  '])->throws(TwoFactorException::class, 'authentication-two-factor.issuer is not set');

    it('throws for a setting below 1', function (): void {
        new TwoFactorConfig(new FakeConfigRepository([
            'authentication-two-factor.recovery_code_count' => 0,
        ]))->recoveryCodeCount();
    })->throws(
        TwoFactorException::class,
        'authentication-two-factor.recovery_code_count must be a positive integer, got 0',
    );
});

<?php

declare(strict_types=1);

use Marko\Core\Environment\AppEnvironment;
use Marko\ErrorsSimple\Environment;

it('detects CLI context from PHP_SAPI', function (): void {
    $environment = new Environment();

    // PHP_SAPI in CLI mode is 'cli'
    expect($environment->isCli())->toBeTrue();
});

it('detects web context from PHP_SAPI', function (): void {
    // Override the SAPI to simulate web context
    $environment = new Environment(sapi: 'apache2handler');

    expect($environment->isWeb())->toBeTrue()
        ->and($environment->isCli())->toBeFalse();
});

it('detects development mode from MARKO_ENV', function (): void {
    $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);

    expect($environment->isDevelopment())->toBeTrue();
});

it('detects development mode from APP_ENV as fallback', function (): void {
    // No MARKO_ENV, but APP_ENV is set
    $environment = new Environment(envVars: ['APP_ENV' => 'development']);

    expect($environment->isDevelopment())->toBeTrue();
});

it('treats an unset environment as production', function (): void {
    // Empty envVars means no environment variables — fail safe and hide error details
    $environment = new Environment(envVars: []);

    expect($environment->isProduction())->toBeTrue()
        ->and($environment->isDevelopment())->toBeFalse();
});

it('prefers MARKO_ENV over APP_ENV', function (): void {
    $environment = new Environment(envVars: ['MARKO_ENV' => 'local', 'APP_ENV' => 'production']);

    expect($environment->isDevelopment())->toBeTrue()
        ->and($environment->isProduction())->toBeFalse();
});

it('treats non-development environments such as staging as production', function (): void {
    $environment = new Environment(envVars: ['APP_ENV' => 'staging']);

    expect($environment->isProduction())->toBeTrue()
        ->and($environment->isDevelopment())->toBeFalse();
});

it('recognizes dev as development', function (): void {
    $environment = new Environment(envVars: ['MARKO_ENV' => 'dev']);

    expect($environment->isDevelopment())->toBeTrue();
});

it('recognizes development as development', function (): void {
    $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);

    expect($environment->isDevelopment())->toBeTrue();
});

it('recognizes local as development', function (): void {
    $environment = new Environment(envVars: ['MARKO_ENV' => 'local']);

    expect($environment->isDevelopment())->toBeTrue();
});

it('recognizes production as production', function (): void {
    $environment = new Environment(envVars: ['MARKO_ENV' => 'production']);

    expect($environment->isProduction())->toBeTrue()
        ->and($environment->isDevelopment())->toBeFalse();
});

it('recognizes prod as production', function (): void {
    $environment = new Environment(envVars: ['MARKO_ENV' => 'prod']);

    expect($environment->isProduction())->toBeTrue()
        ->and($environment->isDevelopment())->toBeFalse();
});

it('is case insensitive for environment values', function (): void {
    $prodUpper = new Environment(envVars: ['MARKO_ENV' => 'PRODUCTION']);
    $prodMixed = new Environment(envVars: ['MARKO_ENV' => 'Production']);

    expect($prodUpper->isProduction())->toBeTrue()
        ->and($prodMixed->isProduction())->toBeTrue();
});

it('provides isCli method', function (): void {
    $environment = new Environment();

    expect($environment->isCli())->toBeBool()
        ->and(method_exists($environment, 'isCli'))->toBeTrue();
});

it('provides isWeb method', function (): void {
    $environment = new Environment();

    expect($environment->isWeb())->toBeBool()
        ->and(method_exists($environment, 'isWeb'))->toBeTrue();
});

it('provides isDevelopment method', function (): void {
    $environment = new Environment();

    expect($environment->isDevelopment())->toBeBool()
        ->and(method_exists($environment, 'isDevelopment'))->toBeTrue();
});

it('provides isProduction method', function (): void {
    $environment = new Environment();

    expect($environment->isProduction())->toBeBool()
        ->and(method_exists($environment, 'isProduction'))->toBeTrue();
});

it('can be overridden for testing', function (): void {
    // Test that both SAPI and environment can be overridden via constructor
    $environment = new Environment(
        sapi: 'fpm-fcgi',
        envVars: ['MARKO_ENV' => 'development'],
    );

    // SAPI override: should not be CLI even though running in CLI
    expect($environment->isCli())->toBeFalse()
        ->and($environment->isWeb())->toBeTrue()
        ->and($environment->isDevelopment())->toBeTrue()
        ->and($environment->isProduction())->toBeFalse();
});

it('delegates to an injected AppEnvironment', function (): void {
    $appEnvironment = new AppEnvironment(['APP_ENV' => 'local']);
    $environment = new Environment(envVars: ['APP_ENV' => 'production'], appEnvironment: $appEnvironment);

    expect($environment->appEnvironment())->toBe($appEnvironment)
        ->and($environment->isDevelopment())->toBeTrue()
        ->and($environment->isProduction())->toBeFalse();
});

it('builds its AppEnvironment from envVars when none is injected', function (): void {
    $environment = new Environment(envVars: ['MARKO_ENV' => 'dev']);

    expect($environment->appEnvironment()->name())->toBe('dev')
        ->and($environment->isDevelopment())->toBeTrue();
});

it('throws warnings only in a development web request', function (string $sapi, string $env, bool $expected): void {
    $environment = new Environment(sapi: $sapi, envVars: ['MARKO_ENV' => $env]);

    expect($environment->throwsWarnings())->toBe($expected);
})->with([
    'development web' => ['fpm-fcgi', 'development', true],
    'local web' => ['apache2handler', 'local', true],
    'production web' => ['fpm-fcgi', 'production', false],
    'staging web' => ['fpm-fcgi', 'staging', false],
    'development cli' => ['cli', 'development', false],
]);

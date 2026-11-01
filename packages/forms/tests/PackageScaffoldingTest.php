<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\Container;
use Marko\Forms\Config\FormsConfig;
use Marko\Forms\Exceptions\FormsException;
use Marko\Forms\SessionFlashedFormState;
use Marko\Session\Contracts\FlashDataInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\SessionFlashData;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;
use Marko\Validation\Contracts\FlashedFormStateInterface;

it('has a composer.json for marko/forms as a marko module', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    expect($composer['name'])->toBe('marko/forms')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer)->not->toHaveKey('version')
        ->and(array_keys($composer['require']))->toBe([
            'php',
            'marko/config',
            'marko/core',
            'marko/routing',
            'marko/security',
            'marko/session',
            'marko/validation',
            'marko/view',
        ])
        ->and($composer['autoload']['psr-4'])->toBe(['Marko\\Forms\\' => 'src/'])
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('ships config with every key FormsConfig reads', function (): void {
    $defaults = require dirname(__DIR__) . '/config/forms.php';

    expect($defaults['never_flash'])->toBe(['password', 'password_confirmation', 'current_password'])
        ->and($defaults['fallback_redirect'])->toBe('/');

    $config = new FormsConfig(new FakeConfigRepository([
        'forms.never_flash' => $defaults['never_flash'],
        'forms.fallback_redirect' => $defaults['fallback_redirect'],
    ]));

    expect($config->neverFlash())->toBe($defaults['never_flash'])
        ->and($config->fallbackRedirect())->toBe('/');
});

it('binds FlashedFormStateInterface to the session-backed reader', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module['bindings'])->toBe([
        FlashedFormStateInterface::class => SessionFlashedFormState::class,
    ]);

    $container = new Container();
    $container->instance(SessionInterface::class, new FakeSession());
    $container->bind(FlashDataInterface::class, SessionFlashData::class);

    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    expect($container->get(FlashedFormStateInterface::class))->toBeInstanceOf(SessionFlashedFormState::class);
});

describe('FormsConfig', function (): void {
    it('throws when a never_flash entry is not a field name', function (mixed $field): void {
        new FormsConfig(new FakeConfigRepository([
            'forms.never_flash' => ['password', $field],
        ]))->neverFlash();
    })->with([[''], ['  '], [42], [null]])->throws(FormsException::class, 'forms.never_flash must list field names');

    it('throws when the fallback redirect is not a local path', function (string $path): void {
        new FormsConfig(new FakeConfigRepository([
            'forms.fallback_redirect' => $path,
        ]))->fallbackRedirect();
    })->with(['https://example.com', '//evil.example', '/\\evil.example', 'home', ''])
        ->throws(FormsException::class, 'forms.fallback_redirect must be a path on this site');

    it('throws when a key is missing from the config', function (): void {
        new FormsConfig(new FakeConfigRepository([]))->fallbackRedirect();
    })->throws(ConfigNotFoundException::class);
});

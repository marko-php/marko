<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\Twig\TwigViewConfig;

it('returns cache_directory from config', function (): void {
    $config = new FakeConfigRepository(['view.cache_directory' => '/tmp/cache']);
    $twigViewConfig = new TwigViewConfig($config);

    expect($twigViewConfig->cacheDirectory())->toBe('/tmp/cache');
});

it('returns extension from config', function (): void {
    $config = new FakeConfigRepository(['view.extension' => '.twig']);
    $twigViewConfig = new TwigViewConfig($config);

    expect($twigViewConfig->extension())->toBe('.twig');
});

it('returns auto_refresh as bool from config', function (): void {
    $config = new FakeConfigRepository(['view.auto_refresh' => true]);
    $twigViewConfig = new TwigViewConfig($config);

    $config2 = new FakeConfigRepository(['view.auto_refresh' => false]);
    $twigViewConfig2 = new TwigViewConfig($config2);

    expect($twigViewConfig->autoRefresh())->toBeTrue()
        ->and($twigViewConfig2->autoRefresh())->toBeFalse();
});

it('returns strict_variables as bool from config', function (): void {
    $config = new FakeConfigRepository(['view.strict_variables' => true]);
    $twigViewConfig = new TwigViewConfig($config);

    $config2 = new FakeConfigRepository(['view.strict_variables' => false]);
    $twigViewConfig2 = new TwigViewConfig($config2);

    expect($twigViewConfig->strictVariables())->toBeTrue()
        ->and($twigViewConfig2->strictVariables())->toBeFalse();
});

it('returns autoescape from config', function (): void {
    $config = new FakeConfigRepository(['view.autoescape' => 'html']);
    $twigViewConfig = new TwigViewConfig($config);

    expect($twigViewConfig->autoescape())->toBe('html');
});

it('returns debug as bool from config', function (): void {
    $config = new FakeConfigRepository(['view.debug' => true]);
    $twigViewConfig = new TwigViewConfig($config);

    $config2 = new FakeConfigRepository(['view.debug' => false]);
    $twigViewConfig2 = new TwigViewConfig($config2);

    expect($twigViewConfig->debug())->toBeTrue()
        ->and($twigViewConfig2->debug())->toBeFalse();
});

it('returns charset from config', function (): void {
    $config = new FakeConfigRepository(['view.charset' => 'UTF-8']);
    $twigViewConfig = new TwigViewConfig($config);

    expect($twigViewConfig->charset())->toBe('UTF-8');
});

it('throws ConfigNotFoundException when a required key is missing', function (): void {
    $config = new FakeConfigRepository([]);
    $twigViewConfig = new TwigViewConfig($config);

    $twigViewConfig->cacheDirectory();
})->throws(ConfigNotFoundException::class);

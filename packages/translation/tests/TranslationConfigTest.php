<?php

declare(strict_types=1);

use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Translation\Config\TranslationConfig;

it('uses FakeConfigRepository in TranslationConfigTest (2 instances)', function () {
    $config1 = new FakeConfigRepository([
        'translation.locale' => 'fr',
        'translation.fallback_locale' => 'en',
    ]);
    $config2 = new FakeConfigRepository([
        'translation.locale' => 'de',
        'translation.fallback_locale' => 'en',
    ]);

    expect($config1)->toBeInstanceOf(FakeConfigRepository::class)
        ->and($config2)->toBeInstanceOf(FakeConfigRepository::class);
});

it('creates TranslationConfig with locale and fallback locale from config', function () {
    $configRepo = new FakeConfigRepository([
        'translation.locale' => 'fr',
        'translation.fallback_locale' => 'en',
    ]);

    $config = new TranslationConfig($configRepo);

    expect($config)->toBeInstanceOf(TranslationConfig::class);
});

it('provides locale and fallbackLocale as readonly properties', function () {
    $configRepo = new FakeConfigRepository([
        'translation.locale' => 'fr',
        'translation.fallback_locale' => 'en',
    ]);

    $config = new TranslationConfig($configRepo);

    expect($config->locale)->toBe('fr')
        ->and($config->fallbackLocale)->toBe('en');

    $reflection = new ReflectionClass($config);
    $localeProperty = $reflection->getProperty('locale');
    $fallbackProperty = $reflection->getProperty('fallbackLocale');

    expect($localeProperty->isReadOnly())->toBeTrue()
        ->and($fallbackProperty->isReadOnly())->toBeTrue();
});

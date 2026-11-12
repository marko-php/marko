<?php

declare(strict_types=1);

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Module\ManifestParser;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Translation\Config\TranslationConfig;
use Marko\Translation\Contracts\TranslationLoaderInterface;
use Marko\Translation\Contracts\TranslatorInterface;
use Marko\Translation\Translator;

it('binds TranslatorInterface to Translator as a class binding', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module['bindings'][TranslatorInterface::class])->toBe(Translator::class);
});

it('builds Translator with the configured locale and fallback locale', function (): void {
    $loader = new class () implements TranslationLoaderInterface
    {
        public function load(
            string $locale,
            string $group,
            ?string $namespace = null,
        ): array {
            return $locale === 'en' ? ['hello' => 'Hello'] : [];
        }
    };
    $container = new Container();
    $container->instance(TranslationLoaderInterface::class, $loader);
    $container->instance(TranslationConfig::class, new TranslationConfig(new FakeConfigRepository([
        'translation.locale' => 'fr',
        'translation.fallback_locale' => 'en',
    ])));
    new BindingRegistry($container)->registerModule(new ManifestParser()->parse(dirname(__DIR__)));

    $translator = $container->get(TranslatorInterface::class);

    expect($translator)->toBeInstanceOf(Translator::class)
        ->and($translator->getLocale())->toBe('fr')
        ->and($translator->get('messages.hello'))->toBe('Hello');
});

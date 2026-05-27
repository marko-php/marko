<?php

declare(strict_types=1);

use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\Twig\TwigEngineFactory;
use Marko\View\Twig\TwigViewConfig;
use Twig\Environment;
use Twig\Extension\EscaperExtension;

function makeTwigViewConfig(array $overrides = []): TwigViewConfig
{
    $defaults = [
        'view.cache_directory' => '/tmp/twig-cache',
        'view.auto_refresh' => true,
        'view.strict_variables' => false,
        'view.autoescape' => 'html',
        'view.debug' => false,
        'view.charset' => 'UTF-8',
    ];

    return new TwigViewConfig(new FakeConfigRepository(array_merge($defaults, $overrides)));
}

describe('TwigEngineFactory', function (): void {
    test('it returns a Twig Environment instance', function (): void {
        $factory = new TwigEngineFactory(makeTwigViewConfig());

        expect($factory->create())->toBeInstanceOf(Environment::class);
    });

    test('it sets the cache directory from config', function (): void {
        $cacheDir = sys_get_temp_dir() . '/twig-test-' . bin2hex(random_bytes(8));
        $factory = new TwigEngineFactory(makeTwigViewConfig(['view.cache_directory' => $cacheDir]));

        expect($factory->create()->getCache())->toBe($cacheDir);
    });

    test('it sets auto_reload from auto_refresh config', function (): void {
        $factoryTrue = new TwigEngineFactory(makeTwigViewConfig(['view.auto_refresh' => true]));
        $factoryFalse = new TwigEngineFactory(makeTwigViewConfig(['view.auto_refresh' => false]));

        expect($factoryTrue->create()->isAutoReload())->toBeTrue()
            ->and($factoryFalse->create()->isAutoReload())->toBeFalse();
    });

    test('it sets strict_variables from config', function (): void {
        $factoryTrue = new TwigEngineFactory(makeTwigViewConfig(['view.strict_variables' => true]));
        $factoryFalse = new TwigEngineFactory(makeTwigViewConfig(['view.strict_variables' => false]));

        expect($factoryTrue->create()->isStrictVariables())->toBeTrue()
            ->and($factoryFalse->create()->isStrictVariables())->toBeFalse();
    });

    test('it sets autoescape from config', function (): void {
        $factory = new TwigEngineFactory(makeTwigViewConfig(['view.autoescape' => 'js']));
        $env = $factory->create();

        $escaper = $env->getExtension(EscaperExtension::class);
        expect($escaper->getDefaultStrategy('template.html.twig'))->toBe('js');
    });

    test('it sets debug from config', function (): void {
        $factoryTrue = new TwigEngineFactory(makeTwigViewConfig(['view.debug' => true]));
        $factoryFalse = new TwigEngineFactory(makeTwigViewConfig(['view.debug' => false]));

        expect($factoryTrue->create()->isDebug())->toBeTrue()
            ->and($factoryFalse->create()->isDebug())->toBeFalse();
    });

    test('it sets charset from config', function (): void {
        $factory = new TwigEngineFactory(makeTwigViewConfig(['view.charset' => 'ISO-8859-1']));

        expect($factory->create()->getCharset())->toBe('ISO-8859-1');
    });
});

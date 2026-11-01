<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Exceptions\InsecureCacheDirectoryException;
use Marko\View\TemplateResolverInterface;
use Marko\View\Twig\ModuleLoader;
use Marko\View\Twig\Tests\Fixtures\PathTemplateResolver;
use Marko\View\Twig\Tests\Fixtures\ViewProviders;
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
        $factory = new TwigEngineFactory(
            makeTwigViewConfig(),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );

        expect($factory->create())->toBeInstanceOf(Environment::class);
    });

    test('it sets the cache directory from config', function (): void {
        $cacheDir = sys_get_temp_dir() . '/twig-test-' . bin2hex(random_bytes(8));
        $factory = new TwigEngineFactory(
            makeTwigViewConfig(['view.cache_directory' => $cacheDir]),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );

        expect($factory->create()->getCache())->toBe($cacheDir);
    });

    test('it sets auto_reload from auto_refresh config', function (): void {
        $factoryTrue = new TwigEngineFactory(
            makeTwigViewConfig(['view.auto_refresh' => true]),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );
        $factoryFalse = new TwigEngineFactory(
            makeTwigViewConfig(['view.auto_refresh' => false]),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );

        expect($factoryTrue->create()->isAutoReload())->toBeTrue()
            ->and($factoryFalse->create()->isAutoReload())->toBeFalse();
    });

    test('it sets strict_variables from config', function (): void {
        $factoryTrue = new TwigEngineFactory(
            makeTwigViewConfig(['view.strict_variables' => true]),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );
        $factoryFalse = new TwigEngineFactory(
            makeTwigViewConfig(['view.strict_variables' => false]),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );

        expect($factoryTrue->create()->isStrictVariables())->toBeTrue()
            ->and($factoryFalse->create()->isStrictVariables())->toBeFalse();
    });

    test('it sets autoescape from config', function (): void {
        $factory = new TwigEngineFactory(
            makeTwigViewConfig(['view.autoescape' => 'js']),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );
        $env = $factory->create();

        $escaper = $env->getExtension(EscaperExtension::class);
        expect($escaper->getDefaultStrategy('template.html.twig'))->toBe('js');
    });

    test('it sets debug from config', function (): void {
        $factoryTrue = new TwigEngineFactory(
            makeTwigViewConfig(['view.debug' => true]),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );
        $factoryFalse = new TwigEngineFactory(
            makeTwigViewConfig(['view.debug' => false]),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );

        expect($factoryTrue->create()->isDebug())->toBeTrue()
            ->and($factoryFalse->create()->isDebug())->toBeFalse();
    });

    test('it sets charset from config', function (): void {
        $factory = new TwigEngineFactory(
            makeTwigViewConfig(['view.charset' => 'ISO-8859-1']),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );

        expect($factory->create()->getCharset())->toBe('ISO-8859-1');
    });
});

describe('TwigEngineFactory cache directory hardening', function (): void {
    beforeEach(function (): void {
        $this->base = sys_get_temp_dir() . '/marko-twig-base-' . bin2hex(random_bytes(8));
        mkdir($this->base, 0o700);
    });

    afterEach(function (): void {
        foreach (array_reverse(glob($this->base . '/{,*/,*/*/}*', GLOB_BRACE | GLOB_MARK)) as $path) {
            str_ends_with($path, '/') ? rmdir($path) : unlink($path);
        }

        rmdir($this->base);
    });

    test('it resolves the default storage/views cache directory under the project base path', function (): void {
        $factory = new TwigEngineFactory(
            makeTwigViewConfig(['view.cache_directory' => 'storage/views']),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths($this->base)),
            new PathTemplateResolver(),
        );

        expect($factory->create()->getCache())->toBe($this->base . '/storage/views')
            ->and(fileperms($this->base . '/storage/views') & 0o777)->toBe(0o700);
    });

    test('it refuses a world-writable cache directory', function (): void {
        $shared = $this->base . '/views';
        mkdir($shared);
        chmod($shared, 0o777);

        $factory = new TwigEngineFactory(
            makeTwigViewConfig(['view.cache_directory' => $shared]),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths($this->base)),
            new PathTemplateResolver(),
        );

        expect(fn (): Environment => $factory->create())
            ->toThrow(InsecureCacheDirectoryException::class, 'world-writable');
    });
});

describe('TwigEngineFactory template loading', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/twig-loader-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/cache', 0o700, true);
        file_put_contents($this->root . '/show.twig', '<h1>{{ title }}</h1>');

        $this->resolver = $this->createStub(TemplateResolverInterface::class);
        $this->resolver->method('resolve')->willReturnMap([['blog::post/show', $this->root . '/show.twig']]);

        $this->factoryFor = fn (bool $autoRefresh): TwigEngineFactory => new TwigEngineFactory(
            makeTwigViewConfig([
                'view.cache_directory' => $this->root . '/cache',
                'view.auto_refresh' => $autoRefresh,
            ]),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            $this->resolver,
        );
    });

    afterEach(function (): void {
        foreach (array_reverse(glob($this->root . '/{,*/,*/*/,*/*/*/}*', GLOB_BRACE | GLOB_MARK)) as $path) {
            str_ends_with($path, '/') ? rmdir($path) : unlink($path);
        }

        rmdir($this->root);
    });

    test('it loads module-namespaced templates through a ModuleLoader on the template resolver', function (): void {
        $environment = ($this->factoryFor)(false)->create();

        expect($environment->getLoader())->toBeInstanceOf(ModuleLoader::class)
            ->and($environment->render('blog::post/show', ['title' => 'Hello']))->toBe('<h1>Hello</h1>');
    });

    test('it builds an auto-reloading environment even when view.auto_refresh is off', function (): void {
        $environment = ($this->factoryFor)(false)->createWithAutoRefresh();

        expect($environment->isAutoReload())->toBeTrue()
            ->and($environment->getLoader())->toBeInstanceOf(ModuleLoader::class)
            ->and($environment->render('blog::post/show', ['title' => 'Hello']))->toBe('<h1>Hello</h1>');
    });
});

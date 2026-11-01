<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Path\ProjectPaths;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Exceptions\TemplatePrecompileException;
use Marko\View\ModuleTemplateResolver;
use Marko\View\TemplatePrecompilerInterface;
use Marko\View\Twig\ModuleLoader;
use Marko\View\Twig\Tests\Fixtures\ViewProviders;
use Marko\View\Twig\TwigEngineFactory;
use Marko\View\Twig\TwigTemplatePrecompiler;
use Marko\View\Twig\TwigView;
use Marko\View\Twig\TwigViewConfig;
use Marko\View\ViewConfig;
use Twig\Error\SyntaxError;

/**
 * A temporary project with one module per entry ('source' => 'path/in/views' => contents).
 *
 * @param array<string, array<string, string>> $modules
 * @return array{root: string, resolver: ModuleTemplateResolver, factory: Closure(bool): TwigEngineFactory}
 */
function twigPrecompileProject(
    array $modules,
): array {
    $root = sys_get_temp_dir() . '/marko-twig-precompile-' . bin2hex(random_bytes(8));
    $manifests = [];

    foreach ($modules as $source => $templates) {
        foreach ($templates as $relativePath => $contents) {
            $file = "$root/$source/resources/views/$relativePath";

            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0755, true);
            }

            file_put_contents($file, $contents);
        }

        $manifests[] = new ModuleManifest(
            name: "$source/blog",
            version: '1.0.0',
            path: "$root/$source",
            source: $source,
        );
    }

    mkdir("$root/cache", 0755, true);

    $viewConfig = new ViewConfig(new ConfigRepository([
        'view' => [
            'cache_directory' => "$root/cache",
            'extension' => '.twig',
            'auto_refresh' => false,
        ],
    ]));

    return [
        'root' => $root,
        'resolver' => new ModuleTemplateResolver(new ModuleRepository($manifests), $viewConfig),
        'factory' => fn (bool $autoRefresh): TwigEngineFactory => new TwigEngineFactory(
            new TwigViewConfig(new FakeConfigRepository([
                'view.cache_directory' => "$root/cache",
                'view.auto_refresh' => $autoRefresh,
                'view.strict_variables' => true,
                'view.autoescape' => 'html',
                'view.debug' => false,
                'view.charset' => 'UTF-8',
            ])),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        ),
    ];
}

/**
 * @return list<string> Compiled template files in the Twig cache
 */
function twigCompiledFiles(
    string $cacheDirectory,
): array {
    $files = [];

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDirectory, FilesystemIterator::SKIP_DOTS),
    ) as $file) {
        if (str_ends_with($file->getPathname(), '.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

function removeTwigPrecompileProject(
    string $path,
): void {
    if (is_file($path) || is_link($path)) {
        unlink($path);

        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item !== '.' && $item !== '..') {
            removeTwigPrecompileProject("$path/$item");
        }
    }

    rmdir($path);
}

describe('TwigTemplatePrecompiler', function (): void {
    test('it implements the marko/view TemplatePrecompilerInterface', function (): void {
        expect(TwigTemplatePrecompiler::class)->toImplement(TemplatePrecompilerInterface::class);
    });

    test(
        'it compiles a template into the cache directory so a following render does not recompile',
        function (): void {
            $project = twigPrecompileProject(['vendor' => ['post/show.twig' => '<h1>Compiled {{ title }}</h1>']]);

            new TwigTemplatePrecompiler($project['factory'](false), $project['resolver'])->precompile(
                'blog::post/show',
            );

            expect(twigCompiledFiles($project['root'] . '/cache'))->toHaveCount(1);

            // auto_refresh is off: a render that compiled again would pick up the edit.
            file_put_contents(
                $project['root'] . '/vendor/resources/views/post/show.twig',
                '<h1>Edited {{ title }}</h1>',
            );
            $view = new TwigView($project['factory'](false)->create(), $project['resolver'], ViewProviders::merger());

            expect($view->renderToString('blog::post/show', ['title' => 'Hello']))->toBe('<h1>Compiled Hello</h1>');

            removeTwigPrecompileProject($project['root']);
        },
    );

    test('it recompiles a template changed since an earlier compile even with auto_refresh off', function (): void {
        $project = twigPrecompileProject(['vendor' => ['post/show.twig' => '<h1>Old {{ title }}</h1>']]);
        $file = $project['root'] . '/vendor/resources/views/post/show.twig';

        // An earlier deploy at the same path left a compiled file behind. It is
        // written without loading its class, as a separate process would have.
        $earlier = $project['factory'](false)->create();
        $earlier->setLoader(new ModuleLoader($project['resolver']));
        $cache = $earlier->getCache(false);
        $key = $cache->generateKey('blog::post/show', $earlier->getTemplateClass('blog::post/show'));
        $cache->write($key, $earlier->compileSource($earlier->getLoader()->getSourceContext('blog::post/show')));
        touch($key, time() - 10);

        file_put_contents($file, '<h1>New {{ title }}</h1>');

        new TwigTemplatePrecompiler($project['factory'](false), $project['resolver'])->precompile('blog::post/show');

        expect(file_get_contents($key))->toContain('New ')
            ->not->toContain('Old ');

        removeTwigPrecompileProject($project['root']);
    });

    test('it compiles the module override that wins resolution', function (): void {
        $project = twigPrecompileProject([
            'app' => ['post/show.twig' => '<h1>App override</h1>'],
            'vendor' => ['post/show.twig' => '<h1>Vendor original</h1>'],
        ]);

        new TwigTemplatePrecompiler($project['factory'](false), $project['resolver'])->precompile('blog::post/show');

        $compiled = twigCompiledFiles($project['root'] . '/cache');

        expect($compiled)->toHaveCount(1)
            ->and(file_get_contents($compiled[0]))->toContain('App override')
            ->not->toContain('Vendor original');

        removeTwigPrecompileProject($project['root']);
    });

    test('it raises a syntax error as TemplatePrecompileException with name, path and line', function (): void {
        $project = twigPrecompileProject(
            ['vendor' => ['post/broken.twig' => "<h1>Fine</h1>\n<p>{% notATag %}</p>\n"]],
        );
        $path = $project['root'] . '/vendor/resources/views/post/broken.twig';

        try {
            new TwigTemplatePrecompiler($project['factory'](false), $project['resolver'])->precompile(
                'blog::post/broken',
            );
            $this->fail('Expected TemplatePrecompileException');
        } catch (TemplatePrecompileException $exception) {
            expect($exception->template)->toBe('blog::post/broken')
                ->and($exception->path)->toBe($path)
                ->and($exception->templateLine)->toBe(2)
                ->and($exception->getMessage())->toContain("Template 'blog::post/broken' failed to compile")
                ->and($exception->getPrevious())->toBeInstanceOf(SyntaxError::class);
        } finally {
            removeTwigPrecompileProject($project['root']);
        }
    });
});

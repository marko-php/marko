<?php

declare(strict_types=1);

use Latte\CompileException;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Path\ProjectPaths;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Exceptions\TemplatePrecompileException;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteTemplatePrecompiler;
use Marko\View\Latte\LatteView;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\Latte\Tests\Fixtures\ViewProviders;
use Marko\View\ModuleTemplateResolver;
use Marko\View\TemplatePrecompilerInterface;
use Marko\View\ViewConfig;

/**
 * A temporary project with one module per entry ('source' => 'path/in/views' => contents).
 *
 * @param array<string, array<string, string>> $modules
 * @return array{root: string, resolver: ModuleTemplateResolver, factory: Closure(bool): LatteEngineFactory}
 */
function lattePrecompileProject(
    array $modules,
): array {
    $root = sys_get_temp_dir() . '/marko-latte-precompile-' . bin2hex(random_bytes(8));
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

    $viewConfig = fn (bool $autoRefresh): ViewConfig => new ViewConfig(new ConfigRepository([
        'view' => [
            'cache_directory' => "$root/cache",
            'extension' => '.latte',
            'auto_refresh' => $autoRefresh,
        ],
    ]));
    $latteViewConfig = new LatteViewConfig(new ConfigRepository(['view' => ['strict_types' => true]]));

    $resolver = new ModuleTemplateResolver(new ModuleRepository($manifests), $viewConfig(false));

    return [
        'root' => $root,
        'resolver' => $resolver,
        'factory' => fn (bool $autoRefresh): LatteEngineFactory => new LatteEngineFactory(
            $viewConfig($autoRefresh),
            $latteViewConfig,
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            $resolver,
        ),
    ];
}

function removeLattePrecompileProject(
    string $path,
): void {
    if (is_file($path) || is_link($path)) {
        unlink($path);

        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item !== '.' && $item !== '..') {
            removeLattePrecompileProject("$path/$item");
        }
    }

    rmdir($path);
}

describe('LatteTemplatePrecompiler', function (): void {
    test('it implements the marko/view TemplatePrecompilerInterface', function (): void {
        expect(LatteTemplatePrecompiler::class)->toImplement(TemplatePrecompilerInterface::class);
    });

    test(
        'it compiles a template into the cache directory so a following render does not recompile',
        function (): void {
            $project = lattePrecompileProject(['vendor' => ['post/show.latte' => '<h1>Compiled {$title}</h1>']]);

            new LatteTemplatePrecompiler($project['factory'](false), $project['resolver'])->precompile(
                'blog::post/show',
            );

            expect(glob($project['root'] . '/cache/*.php'))->toHaveCount(1);

            // auto_refresh is off: a render that compiled again would pick up the edit.
            file_put_contents(
                $project['root'] . '/vendor/resources/views/post/show.latte',
                '<h1>Edited {$title}</h1>',
            );
            $view = new LatteView($project['factory'](false)->create(), ViewProviders::merger());

            expect($view->renderToString('blog::post/show', ['title' => 'Hello']))->toBe('<h1>Compiled Hello</h1>');

            removeLattePrecompileProject($project['root']);
        },
    );

    test('it recompiles a template changed since an earlier compile even with auto_refresh off', function (): void {
        $project = lattePrecompileProject(['vendor' => ['post/show.latte' => '<h1>Old {$title}</h1>']]);
        $file = $project['root'] . '/vendor/resources/views/post/show.latte';

        // An earlier deploy at the same path left a compiled file behind. It is
        // written without loading its class, as a separate process would have.
        $earlier = $project['factory'](false)->create();
        file_put_contents($earlier->getCacheFile('blog::post/show'), $earlier->compile('blog::post/show'));

        file_put_contents($file, '<h1>New {$title}</h1>');
        touch($file, time() + 10);

        new LatteTemplatePrecompiler($project['factory'](false), $project['resolver'])->precompile('blog::post/show');

        expect(file_get_contents($earlier->getCacheFile('blog::post/show')))->toContain('New ')
            ->not->toContain('Old ');

        removeLattePrecompileProject($project['root']);
    });

    test('it compiles the module override that wins resolution', function (): void {
        $project = lattePrecompileProject([
            'app' => ['post/show.latte' => '<h1>App override</h1>'],
            'vendor' => ['post/show.latte' => '<h1>Vendor original</h1>'],
        ]);
        new LatteTemplatePrecompiler($project['factory'](false), $project['resolver'])->precompile('blog::post/show');

        $compiled = glob($project['root'] . '/cache/*.php');

        expect($compiled)->toHaveCount(1)
            ->and(file_get_contents($compiled[0]))->toContain('App override')
            ->not->toContain('Vendor original');

        removeLattePrecompileProject($project['root']);
    });

    test('it raises a syntax error as TemplatePrecompileException with name, path and line', function (): void {
        $project = lattePrecompileProject(['vendor' => ['post/broken.latte' => "<h1>Fine</h1>\n<p>{notATag}</p>\n"]]);
        $path = $project['root'] . '/vendor/resources/views/post/broken.latte';

        try {
            new LatteTemplatePrecompiler($project['factory'](false), $project['resolver'])->precompile(
                'blog::post/broken',
            );
            $this->fail('Expected TemplatePrecompileException');
        } catch (TemplatePrecompileException $exception) {
            expect($exception->template)->toBe('blog::post/broken')
                ->and($exception->path)->toBe($path)
                ->and($exception->templateLine)->toBe(2)
                ->and($exception->getMessage())->toContain("Template 'blog::post/broken' failed to compile")
                ->and($exception->getPrevious())->toBeInstanceOf(CompileException::class);
        } finally {
            removeLattePrecompileProject($project['root']);
        }
    });
});

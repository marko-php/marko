<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Routing\Http\Response;
use Marko\View\Exceptions\NoDriverException;
use Marko\View\Exceptions\TemplateNotFoundException;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\ModuleTemplateResolver;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewConfig;
use Marko\View\ViewInterface;

describe('View Integration', function (): void {
    test('renders template end to end', function (): void {
        // Setup temp directory for templates and cache
        $tempDir = sys_get_temp_dir() . '/marko-integration-' . uniqid();
        mkdir($tempDir . '/resources/views/post', 0755, true);
        mkdir($tempDir . '/cache', 0755, true);

        // Create a template file
        file_put_contents(
            $tempDir . '/resources/views/post/show.latte',
            '<h1>{$title}</h1><p>By {$author}</p>',
        );

        // Setup configuration
        $config = new ConfigRepository([
            'view' => [
                'cache_directory' => $tempDir . '/cache',
                'extension' => '.latte',
                'auto_refresh' => true,
                'strict_types' => true,
            ],
        ]);

        // Setup module repository
        $moduleRepository = new ModuleRepository([
            new ModuleManifest(
                name: 'vendor/blog',
                version: '1.0.0',
                path: $tempDir,
                source: 'vendor',
            ),
        ]);

        // Wire up the full view stack
        $viewConfig = new ViewConfig($config);
        $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
        $engineFactory = new LatteEngineFactory($viewConfig);
        $engine = $engineFactory->create();
        $view = new LatteView($engine, $templateResolver);

        // Render a template
        $response = $view->render('blog::post/show', [
            'title' => 'Hello World',
            'author' => 'John Doe',
        ]);

        // Assert full pipeline works
        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->body())->toBe('<h1>Hello World</h1><p>By John Doe</p>')
            ->and($response->statusCode())->toBe(200)
            ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8');

        // Cleanup
        array_map('unlink', glob($tempDir . '/cache/*'));
        rmdir($tempDir . '/cache');
        unlink($tempDir . '/resources/views/post/show.latte');
        rmdir($tempDir . '/resources/views/post');
        rmdir($tempDir . '/resources/views');
        rmdir($tempDir . '/resources');
        rmdir($tempDir);
    });

    test('module bindings resolve correctly', function (): void {
        // Setup temp directory for templates and cache
        $tempDir = sys_get_temp_dir() . '/marko-integration-' . uniqid();
        mkdir($tempDir . '/resources/views', 0755, true);
        mkdir($tempDir . '/cache', 0755, true);

        file_put_contents(
            $tempDir . '/resources/views/home.latte',
            '<h1>Welcome</h1>',
        );

        // Setup dependencies
        $configArray = [
            'view' => [
                'cache_directory' => $tempDir . '/cache',
                'extension' => '.latte',
                'auto_refresh' => true,
                'strict_types' => true,
            ],
        ];
        $config = new ConfigRepository($configArray);

        $moduleRepository = new ModuleRepository([
            new ModuleManifest(
                name: 'vendor/app',
                version: '1.0.0',
                path: $tempDir,
                source: 'vendor',
            ),
        ]);

        // Setup container with bindings (simulating module bindings)
        $container = new Container();
        $container->instance(ConfigRepositoryInterface::class, $config);
        $container->instance(ModuleRepositoryInterface::class, $moduleRepository);

        // Bind TemplateResolverInterface to ModuleTemplateResolver
        $container->bind(
            TemplateResolverInterface::class,
            function (Container $c): TemplateResolverInterface {
                return new ModuleTemplateResolver(
                    $c->get(ModuleRepositoryInterface::class),
                    $c->get(ViewConfig::class),
                );
            },
        );

        // Bind ViewInterface to LatteView (simulating view-latte module binding)
        $container->bind(
            ViewInterface::class,
            function (Container $c): ViewInterface {
                $engine = $c->get(LatteEngineFactory::class)->create();
                $resolver = $c->get(TemplateResolverInterface::class);

                return new LatteView($engine, $resolver);
            },
        );

        // Resolve ViewInterface through container
        $view = $container->get(ViewInterface::class);

        // Verify bindings resolved correctly
        expect($view)->toBeInstanceOf(ViewInterface::class)
            ->and($view)->toBeInstanceOf(LatteView::class);

        // Verify it works
        $response = $view->render('app::home');
        expect($response->body())->toBe('<h1>Welcome</h1>');

        // Cleanup
        array_map('unlink', glob($tempDir . '/cache/*'));
        rmdir($tempDir . '/cache');
        unlink($tempDir . '/resources/views/home.latte');
        rmdir($tempDir . '/resources/views');
        rmdir($tempDir . '/resources');
        rmdir($tempDir);
    });

    test('template not found produces helpful error', function (): void {
        // Setup temp directory (without the template)
        $tempDir = sys_get_temp_dir() . '/marko-integration-' . uniqid();
        mkdir($tempDir . '/resources/views', 0755, true);
        mkdir($tempDir . '/cache', 0755, true);

        // Setup configuration
        $config = new ConfigRepository([
            'view' => [
                'cache_directory' => $tempDir . '/cache',
                'extension' => '.latte',
                'auto_refresh' => true,
                'strict_types' => true,
            ],
        ]);

        // Setup module repository with multiple modules to show all searched paths
        $moduleRepository = new ModuleRepository([
            new ModuleManifest(
                name: 'app/blog',
                version: '1.0.0',
                path: $tempDir . '/app',
                source: 'app',
            ),
            new ModuleManifest(
                name: 'vendor/blog',
                version: '1.0.0',
                path: $tempDir,
                source: 'vendor',
            ),
        ]);

        // Wire up the full view stack
        $viewConfig = new ViewConfig($config);
        $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
        $engineFactory = new LatteEngineFactory($viewConfig);
        $engine = $engineFactory->create();
        $view = new LatteView($engine, $templateResolver);

        // Try to render a nonexistent template
        try {
            $view->render('blog::nonexistent/template');
            $this->fail('Expected TemplateNotFoundException was not thrown');
        } catch (TemplateNotFoundException $e) {
            // Verify helpful error message
            expect($e->getMessage())->toContain("Template 'blog::nonexistent/template' not found")
                ->and($e->getContext())->toContain($tempDir . '/app/resources/views/nonexistent/template.latte')
                ->and($e->getContext())->toContain($tempDir . '/resources/views/nonexistent/template.latte')
                ->and($e->getSuggestion())->toContain('Verify the template name');
        }

        // Cleanup
        rmdir($tempDir . '/cache');
        rmdir($tempDir . '/resources/views');
        rmdir($tempDir . '/resources');
        rmdir($tempDir);
    });

    test('no driver installed produces helpful error', function (): void {
        // Container without any ViewInterface binding (simulating no driver installed)
        $container = new Container();

        // Try to resolve ViewInterface without a driver
        try {
            $container->get(ViewInterface::class);
            $this->fail('Expected NoDriverException was not thrown');
        } catch (NoDriverException $e) {
            // Verify helpful error message about missing driver
            expect($e->getMessage())->toBe('No view driver installed.')
                ->and($e->getContext())->toContain('no implementation is bound')
                ->and($e->getSuggestion())->toContain('composer require marko/view-latte');
        }
    });
});

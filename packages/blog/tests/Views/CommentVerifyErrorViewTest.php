<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;

describe('Comment Verify Error View', function (): void {
    it('renders verify error template', function (): void {
        $view = createVerifyErrorTestView();

        $html = $view->renderToString('blog::comment/verify-error', [
            'statusCode' => 400,
        ]);

        expect($html)->toContain('Verification Failed');
    });

    it('informs user the link is invalid or expired', function (): void {
        $view = createVerifyErrorTestView();

        $html = $view->renderToString('blog::comment/verify-error', [
            'statusCode' => 400,
        ]);

        expect($html)->toMatch('/invalid|expired/i');
    });

    it('has semantic HTML structure', function (): void {
        $view = createVerifyErrorTestView();

        $html = $view->renderToString('blog::comment/verify-error', [
            'statusCode' => 400,
        ]);

        expect($html)->toContain('<main')
            ->and($html)->toContain('<h1');
    });
});

function createVerifyErrorTestView(): LatteView
{
    $blogPackagePath = dirname(__DIR__, 2);
    $tempCacheDir = sys_get_temp_dir() . '/marko-verify-error-test-' . uniqid();
    mkdir($tempCacheDir, 0755, true);

    $config = new ConfigRepository([
        'view' => [
            'cache_directory' => $tempCacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => true,
        ],
    ]);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'marko/blog',
            version: '1.0.0',
            path: $blogPackagePath,
            source: 'vendor',
        ),
    ]);

    $viewConfig = new ViewConfig($config);
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $engineFactory = new LatteEngineFactory($viewConfig);
    $engine = $engineFactory->create();

    return new LatteView($engine, $templateResolver);
}

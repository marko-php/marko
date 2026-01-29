<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;

describe('Comment Form Component', function (): void {
    it('renders form with POST action to comment endpoint', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '<input type="text" name="hp_abc123" value="" autocomplete="off" tabindex="-1" />',
        ]);

        expect($html)->toContain('<form')
            ->and($html)->toMatch('/method\s*=\s*["\']post["\']/i')
            ->and($html)->toMatch('/action\s*=\s*["\'][^"\']*\/posts\/1\/comments["\']/');
    });

    it('includes name input field with label', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        expect($html)->toContain('<label')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-name["\']/i')
            ->and($html)->toMatch('/id\s*=\s*["\']comment-name["\']/i')
            ->and($html)->toMatch('/name\s*=\s*["\']name["\']/');
    });

    it('includes email input field with label', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        expect($html)->toContain('<label')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-email["\']/i')
            ->and($html)->toMatch('/id\s*=\s*["\']comment-email["\']/i')
            ->and($html)->toMatch('/name\s*=\s*["\']email["\']/');
    });

    it('includes content textarea with label', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        expect($html)->toContain('<label')
            ->and($html)->toContain('<textarea')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-content["\']/i')
            ->and($html)->toMatch('/id\s*=\s*["\']comment-content["\']/i')
            ->and($html)->toMatch('/name\s*=\s*["\']content["\']/');
    });

    it('includes hidden parent_id field for replies', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
            'parentId' => 42,
        ]);

        expect($html)->toMatch('/<input[^>]*type\s*=\s*["\']hidden["\']/i')
            ->and($html)->toMatch('/<input[^>]*name\s*=\s*["\']parent_id["\']/i')
            ->and($html)->toMatch('/value\s*=\s*["\']42["\']/');
    });

    it('includes honeypot field hidden by CSS', function (): void {
        $view = createCommentFormTestView();
        $honeypotField = '<div style="position:absolute;left:-9999px;"><input type="text" name="hp_abc123" value="" autocomplete="off" tabindex="-1" /></div>';

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => $honeypotField,
        ]);

        expect($html)->toContain($honeypotField);
    });

    it('includes submit button', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        expect($html)->toMatch('/<button[^>]*type\s*=\s*["\']submit["\']/');
    });

    it('shows validation error messages when provided', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
            'errors' => [
                'name' => 'Name is required',
                'email' => 'Invalid email format',
                'content' => 'Content must be at least 10 characters',
            ],
        ]);

        expect($html)->toContain('Name is required')
            ->and($html)->toContain('Invalid email format')
            ->and($html)->toContain('Content must be at least 10 characters');
    });

    it('preserves input values on validation failure', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
            'oldInput' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'content' => 'My comment text',
            ],
        ]);

        expect($html)->toMatch('/value\s*=\s*["\']John Doe["\']/i')
            ->and($html)->toMatch('/value\s*=\s*["\']john@example\.com["\']/i')
            ->and($html)->toContain('My comment text');
    });

    it('has proper form accessibility labels', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        // Each input should have an associated label with for attribute
        expect($html)->toMatch('/for\s*=\s*["\']comment-name["\']/i')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-email["\']/i')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-content["\']/i')
            // Form should have aria-label for accessibility
            ->and($html)->toMatch('/aria-label\s*=\s*["\'].*comment.*["\']/i');
    });

    it('includes CSRF token when provided', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
            'csrfToken' => 'test-csrf-token-12345',
        ]);

        expect($html)->toMatch('/<input[^>]*type\s*=\s*["\']hidden["\']/i')
            ->and($html)->toMatch('/<input[^>]*name\s*=\s*["\']_token["\']/i')
            ->and($html)->toMatch('/value\s*=\s*["\']test-csrf-token-12345["\']/');
    });

    it('works without CSRF when token not provided', function (): void {
        $view = createCommentFormTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        // Form should render without _token field
        expect($html)->not->toMatch('/name\s*=\s*["\']_token["\']/');
    });
});

function createCommentFormTestView(): LatteView
{
    $blogPackagePath = dirname(__DIR__, 2);
    $tempCacheDir = sys_get_temp_dir() . '/marko-comment-form-test-' . uniqid();
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

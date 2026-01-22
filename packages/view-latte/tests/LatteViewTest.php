<?php

declare(strict_types=1);

use Latte\CompileException;
use Latte\Engine;
use Marko\Routing\Http\Response;
use Marko\View\Latte\LatteView;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewInterface;

describe('LatteView', function (): void {
    test('implements ViewInterface', function (): void {
        $engine = $this->createMock(Engine::class);
        $resolver = $this->createMock(TemplateResolverInterface::class);

        $view = new LatteView($engine, $resolver);

        expect($view)->toBeInstanceOf(ViewInterface::class);
    });

    test('render returns Response with HTML', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-view-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $templatePath = $cacheDir . '/test.latte';
        file_put_contents($templatePath, '<h1>Hello World</h1>');

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('test::page')
            ->willReturn($templatePath);

        $view = new LatteView($engine, $resolver);
        $response = $view->render('test::page');

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->body())->toBe('<h1>Hello World</h1>')
            ->and($response->headers())->toHaveKey('Content-Type')
            ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8');

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('renderToString returns HTML string', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-view-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $templatePath = $cacheDir . '/test.latte';
        file_put_contents($templatePath, '<p>Test content</p>');

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('test::content')
            ->willReturn($templatePath);

        $view = new LatteView($engine, $resolver);
        $html = $view->renderToString('test::content');

        expect($html)->toBeString()
            ->and($html)->toBe('<p>Test content</p>');

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('passes data to template', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-view-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $templatePath = $cacheDir . '/test.latte';
        file_put_contents($templatePath, '<h1>Hello {$name}</h1><p>{$message}</p>');

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('test::greeting')
            ->willReturn($templatePath);

        $view = new LatteView($engine, $resolver);
        $html = $view->renderToString('test::greeting', [
            'name' => 'World',
            'message' => 'Welcome!',
        ]);

        expect($html)->toBe('<h1>Hello World</h1><p>Welcome!</p>');

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('uses resolver for template paths', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-view-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $templatePath = $cacheDir . '/resolved.latte';
        file_put_contents($templatePath, '<div>Resolved!</div>');

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with('blog::post/show')
            ->willReturn($templatePath);

        $view = new LatteView($engine, $resolver);
        $html = $view->renderToString('blog::post/show');

        expect($html)->toBe('<div>Resolved!</div>');

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('handles template syntax errors', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-view-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $templatePath = $cacheDir . '/invalid.latte';
        // Invalid Latte syntax - unclosed tag
        file_put_contents($templatePath, '{if $condition}<p>Missing end tag');

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('test::invalid')
            ->willReturn($templatePath);

        $view = new LatteView($engine, $resolver);

        expect(fn () => $view->render('test::invalid'))
            ->toThrow(CompileException::class);

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('uses configured extension via resolver', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-view-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        // Use a custom extension to prove LatteView accepts any path from resolver
        $templatePath = $cacheDir . '/template.html.latte';
        file_put_contents($templatePath, '<article>{$title}</article>');

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::article')
            ->willReturn($templatePath);

        $view = new LatteView($engine, $resolver);
        $html = $view->renderToString('blog::article', ['title' => 'Custom Extension']);

        expect($html)->toBe('<article>Custom Extension</article>');

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });
});

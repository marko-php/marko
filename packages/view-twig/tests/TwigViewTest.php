<?php

declare(strict_types=1);

use Marko\Routing\Http\Response;
use Marko\View\TemplateResolverInterface;
use Marko\View\Twig\TwigView;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

describe('TwigView', function (): void {
    function makeTwigEnv(bool $strictVariables = false): Environment
    {
        $loader = new ArrayLoader();

        return new Environment($loader, [
            'autoescape' => 'html',
            'strict_variables' => $strictVariables,
        ]);
    }

    function makeTwigResolver(array $map): TemplateResolverInterface
    {
        $resolver = test()->createStub(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->willReturnCallback(fn (string $template) => $map[$template]
                ?? throw new Exception("Unknown template: $template"));

        return $resolver;
    }

    test('it renders a template and returns an HTML Response', function (): void {
        $templateDir = sys_get_temp_dir() . '/twig-view-test-' . bin2hex(random_bytes(8));
        mkdir($templateDir, 0755, true);

        $templatePath = $templateDir . '/hello.html.twig';
        file_put_contents($templatePath, '<h1>Hello World</h1>');

        $resolver = makeTwigResolver(['test::hello' => $templatePath]);
        $env = makeTwigEnv();

        $view = new TwigView($env, $resolver);
        $response = $view->render('test::hello');

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->body())->toBe('<h1>Hello World</h1>')
            ->and($response->headers())->toHaveKey('Content-Type')
            ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8');

        array_map('unlink', glob($templateDir . '/*'));
        rmdir($templateDir);
    });

    test('it renders a template to a string', function (): void {
        $templateDir = sys_get_temp_dir() . '/twig-view-test-' . bin2hex(random_bytes(8));
        mkdir($templateDir, 0755, true);

        $templatePath = $templateDir . '/content.html.twig';
        file_put_contents($templatePath, '<p>Test content</p>');

        $resolver = makeTwigResolver(['test::content' => $templatePath]);
        $env = makeTwigEnv();

        $view = new TwigView($env, $resolver);
        $html = $view->renderToString('test::content');

        expect($html)->toBeString()
            ->and($html)->toBe('<p>Test content</p>');

        array_map('unlink', glob($templateDir . '/*'));
        rmdir($templateDir);
    });

    test('it passes data variables to the template', function (): void {
        $templateDir = sys_get_temp_dir() . '/twig-view-test-' . bin2hex(random_bytes(8));
        mkdir($templateDir, 0755, true);

        $templatePath = $templateDir . '/greeting.html.twig';
        file_put_contents($templatePath, '<h1>Hello {{ name }}</h1><p>{{ message }}</p>');

        $resolver = makeTwigResolver(['test::greeting' => $templatePath]);
        $env = makeTwigEnv();

        $view = new TwigView($env, $resolver);
        $html = $view->renderToString('test::greeting', [
            'name' => 'World',
            'message' => 'Welcome!',
        ]);

        expect($html)->toBe('<h1>Hello World</h1><p>Welcome!</p>');

        array_map('unlink', glob($templateDir . '/*'));
        rmdir($templateDir);
    });

    test('it resolves module-namespaced template includes via the resolver', function (): void {
        $templateDir = sys_get_temp_dir() . '/twig-view-test-' . bin2hex(random_bytes(8));
        mkdir($templateDir, 0755, true);

        $itemPath = $templateDir . '/item.html.twig';
        file_put_contents($itemPath, '<li>{{ name }}</li>');

        $listPath = $templateDir . '/list.html.twig';
        file_put_contents(
            $listPath,
            '<ul>{% for item in items %}{% include "blog::post/item" with {"name": item} only %}{% endfor %}</ul>',
        );

        $resolver = makeTwigResolver([
            'blog::post/index' => $listPath,
            'blog::post/item' => $itemPath,
        ]);
        $env = makeTwigEnv();

        $view = new TwigView($env, $resolver);
        $html = $view->renderToString('blog::post/index', [
            'items' => ['First', 'Second', 'Third'],
        ]);

        expect($html)->toBe('<ul><li>First</li><li>Second</li><li>Third</li></ul>');

        array_map('unlink', glob($templateDir . '/*'));
        rmdir($templateDir);
    });

    test('it auto-escapes HTML output by default', function (): void {
        $templateDir = sys_get_temp_dir() . '/twig-view-test-' . bin2hex(random_bytes(8));
        mkdir($templateDir, 0755, true);

        $templatePath = $templateDir . '/xss.html.twig';
        file_put_contents($templatePath, '<div>{{ content }}</div>');

        $resolver = makeTwigResolver(['test::xss' => $templatePath]);
        $env = makeTwigEnv();

        $view = new TwigView($env, $resolver);
        $html = $view->renderToString('test::xss', [
            'content' => "<script>alert('xss')</script>",
        ]);

        expect($html)->toContain('&lt;script&gt;')
            ->and($html)->not->toContain('<script>');

        array_map('unlink', glob($templateDir . '/*'));
        rmdir($templateDir);
    });

    test('it throws a Twig error when an undefined variable is used and strict_variables is true', function (): void {
        $templateDir = sys_get_temp_dir() . '/twig-view-test-' . bin2hex(random_bytes(8));
        mkdir($templateDir, 0755, true);

        $templatePath = $templateDir . '/strict.html.twig';
        file_put_contents($templatePath, '<p>{{ undefined_var }}</p>');

        $resolver = makeTwigResolver(['test::strict' => $templatePath]);
        $env = makeTwigEnv(strictVariables: true);

        $view = new TwigView($env, $resolver);

        expect(fn () => $view->renderToString('test::strict'))
            ->toThrow(RuntimeError::class);

        array_map('unlink', glob($templateDir . '/*'));
        rmdir($templateDir);
    });
});

<?php

declare(strict_types=1);

use Latte\Loader;
use Latte\RuntimeException;
use Marko\View\Latte\ModuleLoader;
use Marko\View\TemplateResolverInterface;

describe('ModuleLoader', function (): void {
    test('implements Latte\Loader interface', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $loader = new ModuleLoader($resolver);

        expect($loader)->toBeInstanceOf(Loader::class);
    });

    test('getContent resolves template and returns file contents', function (): void {
        $cacheDir = sys_get_temp_dir() . '/module-loader-test-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $templatePath = $cacheDir . '/test.latte';
        file_put_contents($templatePath, '<h1>Hello</h1>');

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::post/show')
            ->willReturn($templatePath);

        $loader = new ModuleLoader($resolver);
        $content = $loader->getContent('blog::post/show');

        expect($content)->toBe('<h1>Hello</h1>');

        // Cleanup
        unlink($templatePath);
        rmdir($cacheDir);
    });

    test('getContent throws when template file not found', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::missing')
            ->willReturn('/nonexistent/path.latte');

        $loader = new ModuleLoader($resolver);

        expect(fn () => $loader->getContent('blog::missing'))
            ->toThrow(RuntimeException::class);
    });

    test('getReferredName returns namespaced template unchanged', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $loader = new ModuleLoader($resolver);

        $result = $loader->getReferredName('blog::post/list/item', 'blog::post/index');

        expect($result)->toBe('blog::post/list/item');
    });

    test('getReferredName throws for relative paths', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $loader = new ModuleLoader($resolver);

        expect(fn () => $loader->getReferredName('../components/item', 'blog::post/index'))
            ->toThrow(RuntimeException::class, 'must use module namespace format');
    });

    test('getReferredName throws for bare template names', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $loader = new ModuleLoader($resolver);

        expect(fn () => $loader->getReferredName('item', 'blog::post/index'))
            ->toThrow(RuntimeException::class, 'must use module namespace format');
    });

    test('getUniqueId returns resolved absolute path', function (): void {
        $cacheDir = sys_get_temp_dir() . '/module-loader-test-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $templatePath = $cacheDir . '/test.latte';
        file_put_contents($templatePath, '<p>Test</p>');

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::test')
            ->willReturn($templatePath);

        $loader = new ModuleLoader($resolver);
        $uniqueId = $loader->getUniqueId('blog::test');

        expect($uniqueId)->toBe($templatePath);

        // Cleanup
        unlink($templatePath);
        rmdir($cacheDir);
    });
});

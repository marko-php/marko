<?php

declare(strict_types=1);

use Marko\View\Exceptions\TemplateNotFoundException;
use Marko\View\TemplateResolverInterface;
use Marko\View\Twig\ModuleLoader;
use Twig\Error\LoaderError;
use Twig\Source;

describe('ModuleLoader', function (): void {
    it('returns Twig Source containing the template content when getSourceContext is called', function (): void {
        $tmpDir = sys_get_temp_dir() . '/module-loader-twig-test-' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0755, true);

        $templatePath = $tmpDir . '/test.twig';
        file_put_contents($templatePath, '<h1>Hello Twig</h1>');

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::post/show')
            ->willReturn($templatePath);

        $loader = new ModuleLoader($resolver);
        $source = $loader->getSourceContext('blog::post/show');

        expect($source)->toBeInstanceOf(Source::class)
            ->and($source->getCode())->toBe('<h1>Hello Twig</h1>')
            ->and($source->getName())->toBe('blog::post/show')
            ->and($source->getPath())->toBe($templatePath);

        unlink($templatePath);
        rmdir($tmpDir);
    });

    it('returns the resolved absolute path as the cache key', function (): void {
        $tmpDir = sys_get_temp_dir() . '/module-loader-twig-test-' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0755, true);

        $templatePath = $tmpDir . '/test.twig';
        file_put_contents($templatePath, '<p>Cache test</p>');

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::test')
            ->willReturn($templatePath);

        $loader = new ModuleLoader($resolver);

        expect($loader->getCacheKey('blog::test'))->toBe($templatePath);

        unlink($templatePath);
        rmdir($tmpDir);
    });

    it('returns true from isFresh when file modification time is older than the given time', function (): void {
        $tmpDir = sys_get_temp_dir() . '/module-loader-twig-test-' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0755, true);

        $templatePath = $tmpDir . '/test.twig';
        file_put_contents($templatePath, '<p>Fresh test</p>');

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::fresh')
            ->willReturn($templatePath);

        $loader = new ModuleLoader($resolver);
        $futureTime = time() + 3600;

        expect($loader->isFresh('blog::fresh', $futureTime))->toBeTrue();

        unlink($templatePath);
        rmdir($tmpDir);
    });

    it('returns false from isFresh when file modification time is newer than the given time', function (): void {
        $tmpDir = sys_get_temp_dir() . '/module-loader-twig-test-' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0755, true);

        $templatePath = $tmpDir . '/test.twig';
        file_put_contents($templatePath, '<p>Stale test</p>');

        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::stale')
            ->willReturn($templatePath);

        $loader = new ModuleLoader($resolver);
        $pastTime = time() - 3600;

        expect($loader->isFresh('blog::stale', $pastTime))->toBeFalse();

        unlink($templatePath);
        rmdir($tmpDir);
    });

    it('returns true from exists when the resolver finds the template', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::post/show')
            ->willReturn('/some/path/post/show.twig');

        $loader = new ModuleLoader($resolver);

        expect($loader->exists('blog::post/show'))->toBeTrue();
    });

    it('returns false from exists when the resolver cannot find the template', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::missing')
            ->willThrowException(TemplateNotFoundException::forTemplate('blog::missing', []));

        $loader = new ModuleLoader($resolver);

        expect($loader->exists('blog::missing'))->toBeFalse();
    });

    it('does not throw from exists when the resolver throws TemplateNotFoundException', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->willThrowException(TemplateNotFoundException::forTemplate('blog::nope', []));

        $loader = new ModuleLoader($resolver);

        expect(fn () => $loader->exists('blog::nope'))->not->toThrow(LoaderError::class);
    });

    it('returns false from exists when the name lacks module-namespaced format', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $loader = new ModuleLoader($resolver);

        expect($loader->exists('plain-template'))->toBeFalse();
    });

    it('throws LoaderError from getSourceContext when the name lacks module-namespaced format', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $loader = new ModuleLoader($resolver);

        expect(fn () => $loader->getSourceContext('plain-template'))
            ->toThrow(LoaderError::class, 'module namespace format');
    });

    it('throws LoaderError from getCacheKey when the name lacks module-namespaced format', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $loader = new ModuleLoader($resolver);

        expect(fn () => $loader->getCacheKey('plain-template'))
            ->toThrow(LoaderError::class, 'module namespace format');
    });

    it('throws LoaderError from getSourceContext when the resolved file cannot be read', function (): void {
        $resolver = $this->createMock(TemplateResolverInterface::class);
        $resolver->method('resolve')
            ->with('blog::unreadable')
            ->willReturn('/nonexistent/path/template.twig');

        $loader = new ModuleLoader($resolver);

        expect(fn () => $loader->getSourceContext('blog::unreadable'))
            ->toThrow(LoaderError::class);
    });
});

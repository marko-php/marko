<?php

declare(strict_types=1);

it('has composer.json with name marko/docs-markdown and PSR-4 namespace Marko\\DocsMarkdown\\', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect(file_exists($composerPath))->toBeTrue()
        ->and($composer['name'])->toBe('marko/docs-markdown')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\DocsMarkdown\\')
        ->and($composer['autoload']['psr-4']['Marko\\DocsMarkdown\\'])->toBe('src/');
});

it('preserves the original navigation metadata file or equivalent index', function (): void {
    $indexPath = dirname(__DIR__, 2) . '/docs/index.mdx';

    expect(file_exists($indexPath))->toBeTrue();
});

it('ships docs content under docs/ inside the package', function (): void {
    $docsPath = dirname(__DIR__, 2) . '/docs';

    expect(is_dir($docsPath))->toBeTrue();

    $files = glob($docsPath . '/**/*.md');

    expect($files)->not->toBeEmpty();
});

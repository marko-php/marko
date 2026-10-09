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

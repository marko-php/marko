<?php

declare(strict_types=1);

use Marko\DocsMarkdown\MarkdownRepository;

it('declares docsPath for MarkdownRepository as a literal path', function (): void {
    $packageRoot = dirname(__DIR__, 2);
    $module = require $packageRoot . '/module.php';

    expect($module['bindings'])->not->toHaveKey(MarkdownRepository::class)
        ->and($module['singletons'])->toBe([MarkdownRepository::class])
        ->and($module['arguments'][MarkdownRepository::class])->toBe(['docsPath' => $packageRoot . '/docs']);
});

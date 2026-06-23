<?php

declare(strict_types=1);

$docsPage = dirname(__DIR__, 4) . '/packages/docs-markdown/docs/packages/devai.md';
$readmePath = dirname(__DIR__, 2) . '/README.md';

it('documents the marker-delimited override model on the devai docs page', function () use ($docsPage): void {
    $content = (string) file_get_contents($docsPage);

    expect($content)->toContain('<!-- BEGIN marko:devai -->')
        ->and($content)->toContain('<!-- END marko:devai -->');
});

it('states that content outside markers is never modified', function () use ($docsPage): void {
    $content = (string) file_get_contents($docsPage);

    expect($content)->toContain('outside')
        ->and(
            str_contains($content, 'never modified')
            || str_contains($content, 'never touched')
            || str_contains($content, 'never change'),
        )->toBeTrue();
});

it('states that removing markers makes devai stop managing the file', function () use ($docsPage): void {
    $content = (string) file_get_contents($docsPage);

    expect(
        str_contains($content, 'removing the markers')
        || str_contains($content, 'remove the markers')
        || str_contains($content, 'Removing the markers')
        || str_contains($content, 'Remove the markers'),
    )->toBeTrue()
        ->and(
            str_contains($content, 'stop managing')
            || str_contains($content, 'stops managing')
            || str_contains($content, 'back off')
            || str_contains($content, 'backs off')
            || str_contains($content, 'full ownership'),
        )->toBeTrue();
});

it('keeps the README a slim pointer per docs standards', function () use ($readmePath): void {
    $content = (string) file_get_contents($readmePath);

    // Must have the required slim-pointer sections
    expect($content)->toContain('## Installation')
        ->and($content)->toContain('## Documentation')
        ->and($content)->toContain('marko.build/docs/packages/devai');

    // Must NOT be bloated with override-model detail (that belongs in the docs page)
    expect(str_contains($content, '<!-- BEGIN marko:devai -->'))->toBeFalse();
});

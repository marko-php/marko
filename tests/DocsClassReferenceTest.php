<?php

declare(strict_types=1);

use Marko\Tests\Support\DocsClassReference\DocsClassReferenceScanner;

require_once __DIR__ . '/Support/DocsClassReference/DocsClassReferenceScanner.php';

/*
 * Tests the scanner behind `composer docs:lint` against fixtures. The real
 * docs are checked by `composer docs:lint` in the CI Lint job, never here:
 * tests do not read documentation.
 */

$repositoryRoot = dirname(__DIR__);
$packagesRoot = $repositoryRoot . '/packages';
$fixturePackagesRoot = __DIR__ . '/Fixtures/DocsClassReference/packages';

/**
 * @return array{exitCode: int, output: string}
 */
function runDocsLint(string ...$arguments): array
{
    $command = array_merge([PHP_BINARY, dirname(__DIR__) . '/bin/docs-lint.php'], $arguments);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return ['exitCode' => proc_close($process), 'output' => $output];
}

it('fails docs:lint with file and line for every unresolved Marko class', function () use (
    $fixturePackagesRoot,
): void {
    $result = runDocsLint('--docs-root=' . $fixturePackagesRoot);

    expect($result['exitCode'])->toBe(1)
        ->and($result['output'])
        ->toContain('demo/README.md:7 references missing class Marko\\Nope\\Thing')
        ->toContain('nested/section/page.md:13 references missing class Marko\\Missing\\Fqcn')
        ->toContain('DocsClassReference/README.md:4 references missing class Marko\\Root\\Missing')
        ->not->toContain('Marko\\Routing\\Http\\Response');
});

it('fails docs:lint loudly when it finds no Markdown to scan', function (): void {
    $base = sys_get_temp_dir() . '/marko-docs-lint-empty-' . uniqid();
    mkdir($base . '/packages', recursive: true);

    try {
        $result = runDocsLint('--docs-root=' . $base . '/packages');
    } finally {
        rmdir($base . '/packages');
        rmdir($base);
    }

    expect($result['exitCode'])->toBe(1)
        ->and($result['output'])->toContain('found no Markdown files');
});

it('discovers package READMEs and nested docs pages', function () use ($fixturePackagesRoot): void {
    $files = DocsClassReferenceScanner::discoverMarkdownFiles($fixturePackagesRoot);

    expect($files)->toBe([
        $fixturePackagesRoot . '/demo/README.md',
        $fixturePackagesRoot . '/docs-markdown/docs/nested/section/page.md',
    ]);
});

it('reports a bogus Marko class added to a README with its file and line', function () use (
    $packagesRoot,
    $fixturePackagesRoot,
): void {
    $readme = $fixturePackagesRoot . '/demo/README.md';
    $scanner = new DocsClassReferenceScanner($packagesRoot);

    expect($scanner->findUnresolved([$readme]))->toBe([
        ['file' => $readme, 'line' => 7, 'class' => 'Marko\Nope\Thing'],
    ]);
});

it('ignores Marko references outside fenced PHP code blocks', function () use (
    $packagesRoot,
    $fixturePackagesRoot,
): void {
    $scanner = new DocsClassReferenceScanner($packagesRoot);

    $classes = array_column($scanner->extractReferences($fixturePackagesRoot . '/demo/README.md'), 'class');

    expect($classes)->toBe(['Marko\Routing\Http\Response', 'Marko\Nope\Thing']);
});

it('handles indented fences, group uses, FQCNs, namespaces and function imports', function () use (
    $packagesRoot,
    $fixturePackagesRoot,
): void {
    $page = $fixturePackagesRoot . '/docs-markdown/docs/nested/section/page.md';
    $scanner = new DocsClassReferenceScanner($packagesRoot);

    expect($scanner->extractReferences($page))->toBe([
        ['file' => $page, 'line' => 9, 'class' => 'Marko\Routing\Attributes\Get'],
        ['file' => $page, 'line' => 9, 'class' => 'Marko\Routing\Attributes\Post'],
        ['file' => $page, 'line' => 10, 'class' => 'Marko\Fictional\Example\Service'],
        ['file' => $page, 'line' => 12, 'class' => 'Marko\Routing\Http\Response'],
        ['file' => $page, 'line' => 13, 'class' => 'Marko\Missing\Fqcn'],
    ])->and(array_column($scanner->findUnresolved([$page]), 'class'))->toBe([
        'Marko\Fictional\Example\Service',
        'Marko\Missing\Fqcn',
    ]);
});

it('skips references under an allowlisted fictional namespace prefix', function () use (
    $packagesRoot,
    $fixturePackagesRoot,
): void {
    $page = $fixturePackagesRoot . '/docs-markdown/docs/nested/section/page.md';
    $scanner = new DocsClassReferenceScanner($packagesRoot, ['Marko\Fictional\\']);

    expect(array_column($scanner->findUnresolved([$page]), 'class'))->toBe(['Marko\Missing\Fqcn']);
});

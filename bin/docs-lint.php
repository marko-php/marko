<?php

declare(strict_types=1);

/*
 * docs:lint — fails when a fenced PHP code block in the repository README, a
 * package README or a docs page references a `Marko\...` class that does not exist.
 *
 * Classes resolve through the PSR-4 maps in packages/*\/composer.json (file
 * existence only, nothing is autoloaded), so the result depends only on the
 * files in the repository and runs in well under a second.
 *
 * Fictional example modules should use a non-Marko namespace (`App\`, `Acme\`).
 * If one genuinely has to live under `Marko\`, add its namespace prefix to
 * ALLOWED_FICTIONAL_PREFIXES rather than weakening the scan.
 *
 * Usage:
 *   php bin/docs-lint.php [--docs-root=<dir>]
 *
 * --docs-root scans another directory laid out like packages/ (used by the
 * scanner's own tests); classes still resolve against the real packages.
 *
 * Exits 1 on any unresolved class, or when no Markdown files are found.
 */

use Marko\Tests\Support\DocsClassReference\DocsClassReferenceScanner;

require_once dirname(__DIR__) . '/tests/Support/DocsClassReference/DocsClassReferenceScanner.php';

const ALLOWED_FICTIONAL_PREFIXES = [];

$repositoryRoot = dirname(__DIR__);
$packagesRoot = $repositoryRoot . '/packages';
$options = getopt('', ['docs-root:']);
$docsRoot = is_string($options['docs-root'] ?? null) ? rtrim($options['docs-root'], '/') : $packagesRoot;

$files = DocsClassReferenceScanner::discoverMarkdownFiles($docsRoot);

// The repository README sits one level above the packages directory.
if (is_file(dirname($docsRoot) . '/README.md')) {
    $files[] = dirname($docsRoot) . '/README.md';
}

if ($files === []) {
    fwrite(STDERR, "docs:lint found no Markdown files under $docsRoot. Refusing to pass an empty scan.\n");
    exit(1);
}

$scanner = new DocsClassReferenceScanner($packagesRoot, ALLOWED_FICTIONAL_PREFIXES);
$unresolved = $scanner->findUnresolved($files);

if ($unresolved !== []) {
    foreach ($unresolved as $reference) {
        fwrite(STDERR, sprintf(
            "%s:%d references missing class %s\n",
            str_replace($repositoryRoot . '/', '', $reference['file']),
            $reference['line'],
            $reference['class'],
        ));
    }

    fwrite(STDERR, sprintf(
        "\ndocs:lint failed: %d missing class reference(s). Fix the docs, or the class name.\n",
        count($unresolved),
    ));
    exit(1);
}

echo sprintf("docs:lint passed: %d Markdown files, every Marko class reference resolves.\n", count($files));

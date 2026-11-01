<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\View\ViewInterface;

/*
 * view:cache through the real module wiring (#567): a fixture app booted with
 * one installed view engine compiles every template ahead of time, renders
 * from that cache with auto_refresh off, and fails naming the file and line
 * of a broken template. No services are needed, so these run in every
 * `composer test`.
 */

afterEach(function (): void {
    if (isset($this->project)) {
        removeIntegrationProject($this->project);
    }
});

/**
 * Build and boot the view fixture with the given engine package installed.
 *
 * @throws Throwable
 */
function bootViewCacheApp(
    object $test,
    string $engine,
): Application {
    $test->project = buildIntegrationProject(
        __DIR__ . '/ViewFixture',
        ['clock', 'config', 'core', 'routing', 'view', $engine],
    );

    return Application::boot($test->project);
}

/**
 * @return list<string> Compiled template files under the project's view cache
 */
function viewCacheCompiledFiles(
    string $project,
): array {
    $directory = "$project/storage/views";

    if (!is_dir($directory)) {
        return [];
    }

    $files = [];

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    ) as $file) {
        if (str_ends_with($file->getPathname(), '.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

dataset('view engines', [
    'latte' => ['view-latte', 'latte', "{block content}\n<p>{notATag}</p>\n{/block}\n"],
    'twig' => ['view-twig', 'twig', "{% block content %}\n<p>{% notATag %}</p>\n{% endblock %}\n"],
]);

it('compiles every template with the installed engine and renders from that cache', function (
    string $engine,
    string $extension,
): void {
    $app = bootViewCacheApp($this, $engine);

    $result = runIntegrationCommand($app, 'view:cache');

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($result['output'])->toContain('Compiled 2 templates.')
        ->and(viewCacheCompiledFiles($this->project))->toHaveCount(2);

    // auto_refresh is off in the fixture: rendering an edited template that
    // still shows the old markup proves the precompiled file was used.
    file_put_contents(
        "$this->project/app/shop/resources/views/product/show.$extension",
        $extension === 'latte'
            ? "{layout 'shop::layout/base'}\n{block content}<h2>edited</h2>{/block}\n"
            : "{% extends 'shop::layout/base' %}\n{% block content %}<h2>edited</h2>{% endblock %}\n",
    );

    $html = $app->container->get(ViewInterface::class)->renderToString('shop::product/show', ['name' => 'Lamp']);

    expect($html)->toContain('<h1>Lamp</h1>')
        ->and($html)->toContain('<title>Shop</title>')
        ->and($html)->not->toContain('edited');
})->with('view engines');

it('fails naming the path and line of a broken template and still compiles the rest', function (
    string $engine,
    string $extension,
    string $broken,
): void {
    $app = bootViewCacheApp($this, $engine);
    $path = "$this->project/app/shop/resources/views/product/broken.$extension";
    file_put_contents($path, $broken);

    $result = runIntegrationCommand($app, 'view:cache');

    expect($result['exitCode'])->toBe(1)
        ->and($result['output'])->toContain("Template 'shop::product/broken' failed to compile")
        ->and($result['output'])->toContain("in $path:2")
        ->and($result['output'])->toContain('Compiled 2 of 3 templates; 1 failed.')
        ->and(viewCacheCompiledFiles($this->project))->toHaveCount(2);
})->with('view engines');

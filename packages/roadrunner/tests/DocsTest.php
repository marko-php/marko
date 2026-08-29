<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests;

describe('Roadrunner package documentation', function (): void {
    it('ships a readme following the package readme standards', function (): void {
        $readmePath = dirname(__DIR__) . '/README.md';

        expect(file_exists($readmePath))->toBeTrue();

        $readme = file_get_contents($readmePath);

        expect($readme)->toContain('# marko/roadrunner')
            ->and($readme)->toContain('## Installation')
            ->and($readme)->toContain('composer require marko/roadrunner')
            ->and($readme)->toContain('## Quick Example')
            ->and($readme)->toContain('## Documentation')
            ->and($readme)->toContain('https://marko.build/docs/packages/roadrunner/');
    });

    it('ships a docs page for the roadrunner package', function (): void {
        $docsPath = monorepoRootPath() . '/packages/docs-markdown/docs/packages/roadrunner.md';

        expect(file_exists($docsPath))->toBeTrue();

        $docs = file_get_contents($docsPath);

        expect($docs)->toContain('title: marko/roadrunner')
            ->and($docs)->toContain('## Installation')
            ->and($docs)->toContain('rr:serve')
            ->and($docs)->toContain('vendor/marko/roadrunner/worker.php')
            ->and($docs)->toContain('.rr.yaml')
            ->and($docs)->toContain('http.static.dir')
            ->and($docs)->toContain('pool.max_jobs')
            ->and($docs)->toContain('pool.supervisor.max_worker_memory')
            ->and($docs)->toContain('server.env.MARKO_BASE_PATH');
    });

    it('documents the unsupported packages and the reason for each', function (): void {
        $docsPath = monorepoRootPath() . '/packages/docs-markdown/docs/packages/roadrunner.md';
        $docs = file_get_contents($docsPath);

        expect($docs)->toContain('marko/sse')
            ->and($docs)->toContain('acknowledged_unsafe_packages')
            ->and($docs)->toContain('StreamingResponseException')
            ->and($docs)->toContain('marko/debugbar')
            ->and($docs)->toContain('ob_start()');
    });

    it('documents that file uploads are unsupported', function (): void {
        $docsPath = monorepoRootPath() . '/packages/docs-markdown/docs/packages/roadrunner.md';
        $docs = file_get_contents($docsPath);

        expect($docs)->toContain('File Uploads')
            ->and($docs)->toContain('UploadedFilesNotSupportedException')
            ->and($docs)->toContain('$_FILES');
    });

    it('documents the session cookie caveat', function (): void {
        $docsPath = monorepoRootPath() . '/packages/docs-markdown/docs/packages/roadrunner.md';
        $docs = file_get_contents($docsPath);

        expect($docs)->toContain('Session Cookie Caveat')
            ->and($docs)->toContain('SessionMiddleware')
            ->and($docs)->toContain('Set-Cookie');
    });

    it('documents the stdout restriction', function (): void {
        $docsPath = monorepoRootPath() . '/packages/docs-markdown/docs/packages/roadrunner.md';
        $docs = file_get_contents($docsPath);

        expect($docs)->toContain('Do Not Write to STDOUT')
            ->and($docs)->toContain('var_dump()')
            ->and($docs)->toContain('STDERR');
    });

    it('documents the reset lifecycle and the stateful singleton rule', function (): void {
        $docsPath = monorepoRootPath() . '/packages/docs-markdown/docs/packages/roadrunner.md';
        $docs = file_get_contents($docsPath);

        expect($docs)->toContain('ResettableInterface')
            ->and($docs)->toContain('cross-user leak')
            ->and($docs)->toContain('SessionGuard')
            ->and($docs)->toContain('Inertia::$shared')
            ->and($docs)->toContain('roadrunner-state-leaks');
    });

    it('lists the package in the architecture package inventory', function (): void {
        $architecturePath = monorepoRootPath() . '/.claude/architecture.md';
        $architecture = file_get_contents($architecturePath);

        expect($architecture)->toContain('## Package Inventory')
            ->and($architecture)->toContain('`marko/roadrunner`');
    });
});

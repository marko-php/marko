<?php

declare(strict_types=1);

$deployDocsPath = dirname(__DIR__) . '/.github/workflows/deploy-docs.yml';
$deployDocs = file_exists($deployDocsPath) ? file_get_contents($deployDocsPath) : '';

it('runs one docs deploy at a time so two pushes never race to push marko.build', function () use ($deployDocs): void {
    $beforeJobs = substr($deployDocs, 0, (int) strpos($deployDocs, 'jobs:'));

    expect($beforeJobs)
        ->toContain('concurrency:')
        ->toContain('group: deploy-docs');
});

it('lets a running docs deploy finish instead of cancelling it mid-push', function () use ($deployDocs): void {
    expect($deployDocs)->toContain('cancel-in-progress: false');
});

<?php

declare(strict_types=1);

$binMarkoPath = dirname(__DIR__) . '/bin/marko';

it('has PHP shebang line for direct execution', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    expect($content)->toStartWith('#!/usr/bin/env php');
});

it('requires own autoloader for Marko\\Cli classes', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    // Should check multiple autoloader locations
    expect($content)->toContain('__DIR__')
        ->and($content)->toContain('vendor/autoload.php')
        ->and($content)->toContain('require')
        ->and($content)->toContain('file_exists');
});

it('instantiates CliKernel', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    expect($content)->toContain('use Marko\Cli\CliKernel')
        ->and($content)->toContain('new CliKernel')
        ->and($content)->toContain('projectFinder: $projectFinder');
});

it('passes argv to kernel run method', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    expect($content)->toContain('->run($argv)');
});

it('exits with code returned from kernel', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    expect($content)->toContain('exit((new CliKernel(')
        ->and($content)->toContain('))->run($argv));');
});

it('loads the project autoloader when a project root is found', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    expect($content)->toContain('use Marko\Cli\ProjectFinder')
        ->and($content)->toContain('$projectFinder = new ProjectFinder()')
        ->and($content)->toContain('$projectFinder->find()')
        ->and($content)->toContain('require_once "{$projectRoot}/vendor/autoload.php"');
});

it('handles uncaught exceptions gracefully', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    // Should have try-catch around main logic
    expect($content)->toContain('try')
        ->and($content)->toContain('catch')
        ->and($content)->toContain('Throwable');
});

it('displays error message on failure', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    // Should write error to STDERR
    expect($content)->toContain('STDERR')
        ->and($content)->toContain('$e->getMessage()');
});

it('returns exit code 1 on unhandled error', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    // Should exit(1) in catch block
    expect($content)->toContain('exit(1)');
});

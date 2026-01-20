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
        ->and($content)->toContain('new CliKernel');
});

it('passes argv to kernel run method', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    expect($content)->toContain('$kernel->run($argv)');
});

it('exits with code returned from kernel', function () use ($binMarkoPath) {
    $content = file_get_contents($binMarkoPath);

    // Should capture exit code and use it
    expect($content)->toContain('$exitCode')
        ->and($content)->toContain('exit($exitCode)');
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

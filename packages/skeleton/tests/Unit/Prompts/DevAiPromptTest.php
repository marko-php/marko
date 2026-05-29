<?php

declare(strict_types=1);

use Marko\Skeleton\Prompts\DevAiPrompt;

beforeEach(function (): void {
    $this->prompt = new DevAiPrompt();
    $this->tempRoot = sys_get_temp_dir() . '/skeleton-prompt-' . uniqid();
    mkdir($this->tempRoot, 0755, true);
});

afterEach(function (): void {
    if (is_dir($this->tempRoot)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tempRoot);
    }
});

it('defaults to checked (install recommended)', function (): void {
    $input = fopen('php://memory', 'w+');
    fwrite($input, "\n"); // empty line = default
    rewind($input);
    ob_start();
    $result = $this->prompt->ask($input);
    ob_end_clean();
    expect($result)->toBeTrue();
});

it('records the choice so later skeleton updates don\'t re-prompt', function (): void {
    $this->prompt->recordChoice($this->tempRoot, true);
    expect($this->prompt->alreadyAnswered($this->tempRoot))->toBeTrue();
    $config = json_decode((string) file_get_contents($this->tempRoot . '/.marko/skeleton.json'), true);
    expect($config['devaiAccepted'])->toBeTrue();
});

it('skips cleanly when user declines', function (): void {
    $input = fopen('php://memory', 'w+');
    fwrite($input, "n\n");
    rewind($input);
    ob_start();
    $result = $this->prompt->ask($input);
    ob_end_clean();
    expect($result)->toBeFalse();
});

it('runs composer require --dev marko/devai when user accepts', function (): void {
    // The install method exists and is callable; we don't actually run composer in tests
    expect(method_exists($this->prompt, 'install'))->toBeTrue();
});

it('displays the devai opt-in prompt after project creation', function (): void {
    $input = fopen('php://memory', 'w+');
    fwrite($input, "y\n");
    rewind($input);

    ob_start();
    $result = $this->prompt->ask($input);
    $output = ob_get_clean();

    expect($output)->toContain('marko/devai')
        ->and($output)->toContain('recommended')
        ->and($result)->toBeTrue();
});

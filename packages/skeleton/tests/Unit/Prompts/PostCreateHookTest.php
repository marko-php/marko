<?php

declare(strict_types=1);

use Marko\Skeleton\Prompts\DevAiPrompt;
use Marko\Skeleton\Prompts\PostCreateHook;

beforeEach(function (): void {
    $this->tempRoot = sys_get_temp_dir() . '/skeleton-postcreate-' . uniqid();
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

it('runs marko devai:install after devai is added', function (): void {
    $stubPrompt = new class () extends DevAiPrompt
    {
        public bool $installCalled = false;

        public function ask(mixed $input = null): bool
        {
            return true;
        }

        public function install(string $projectRoot): int
        {
            $this->installCalled = true;

            return 0;
        }
    };

    $hook = new PostCreateHook($stubPrompt);
    ob_start();
    $hook->run($this->tempRoot, interactive: false);
    ob_end_clean();

    expect($stubPrompt->installCalled)->toBeTrue();
});

it('respects non-interactive mode by defaulting to sensible agent auto-detection and vec docs driver', function (): void {
    $stubPrompt = new class () extends DevAiPrompt
    {
        public ?bool $askCalled = null;

        public function ask(mixed $input = null): bool
        {
            $this->askCalled = true;

            return true;
        }

        public function install(string $projectRoot): int
        {
            return 0;
        }
    };

    $hook = new PostCreateHook($stubPrompt);
    ob_start();
    $hook->run($this->tempRoot, interactive: false);
    ob_end_clean();

    expect($stubPrompt->askCalled)->toBeNull();
});

it('aborts cleanly if devai:install fails without rolling back composer require', function (): void {
    $stubPrompt = new class () extends DevAiPrompt
    {
        public function ask(mixed $input = null): bool
        {
            return true;
        }

        public function install(string $projectRoot): int
        {
            return 1;
        }
    };

    $hook = new PostCreateHook($stubPrompt);
    ob_start();
    $code = $hook->run($this->tempRoot, interactive: false);
    ob_end_clean();

    expect($code)->not->toBe(0);
});

it('prints a clear next-step message if the user skipped devai', function (): void {
    $stubPrompt = new class () extends DevAiPrompt
    {
        public function ask(mixed $input = null): bool
        {
            return false;
        }

        public function install(string $projectRoot): int
        {
            throw new RuntimeException('Should not be called');
        }
    };

    $hook = new PostCreateHook($stubPrompt);
    ob_start();
    $code = $hook->run($this->tempRoot, interactive: true);
    $output = ob_get_clean();

    expect($code)->toBe(0)
        ->and($output)->toContain('Skipped marko/devai')
        ->and($output)->toContain('marko devai:install');
});

<?php

declare(strict_types=1);

use Marko\Translation\Exceptions\TranslationException;
use Marko\Translation\File\Loader\FileTranslationLoader;

function pathValidationCreateTempDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/marko_pathval_test_' . bin2hex(random_bytes(8));
    mkdir($tmpDir . '/lang/en', 0777, true);

    return $tmpDir;
}

function pathValidationCleanupDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }

    rmdir($dir);
}

it('rejects a locale containing a path traversal sequence (../)', function (): void {
    $tmpDir = pathValidationCreateTempDir();

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $loader->load('../etc', 'messages');
    } finally {
        pathValidationCleanupDir($tmpDir);
    }
})->throws(TranslationException::class);

it('rejects a group containing a path traversal sequence (../)', function (): void {
    $tmpDir = pathValidationCreateTempDir();

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $loader->load('en', '../secret');
    } finally {
        pathValidationCleanupDir($tmpDir);
    }
})->throws(TranslationException::class);

it('rejects a locale containing a null byte', function (): void {
    $tmpDir = pathValidationCreateTempDir();

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $loader->load("en\0", 'messages');
    } finally {
        pathValidationCleanupDir($tmpDir);
    }
})->throws(TranslationException::class);

it('rejects a group containing a slash or dot path separator', function (): void {
    $tmpDir = pathValidationCreateTempDir();

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $loader->load('en', 'sub/group');
    } finally {
        pathValidationCleanupDir($tmpDir);
    }
})->throws(TranslationException::class);

it('rejects an invalid namespace path segment in the namespaced branch', function (): void {
    $tmpDir = pathValidationCreateTempDir();

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $loader->load('en', 'messages', '../evil');
    } finally {
        pathValidationCleanupDir($tmpDir);
    }
})->throws(TranslationException::class);

it('rejects an empty locale or group segment', function (): void {
    $tmpDir = pathValidationCreateTempDir();

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $loader->load('', 'messages');
    } finally {
        pathValidationCleanupDir($tmpDir);
    }
})->throws(TranslationException::class);

it('still loads a valid locale and group from the non-namespaced branch', function (): void {
    $tmpDir = pathValidationCreateTempDir();
    file_put_contents($tmpDir . '/lang/en/messages.php', '<?php return ["hello" => "Hello!"];');

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $result = $loader->load('en', 'messages');

        expect($result)->toBe(['hello' => 'Hello!']);
    } finally {
        pathValidationCleanupDir($tmpDir);
    }
});

it('still loads a valid namespaced locale and group from a registered namespace', function (): void {
    $tmpDir = pathValidationCreateTempDir();
    $nsDir = sys_get_temp_dir() . '/marko_pathval_ns_' . bin2hex(random_bytes(8));
    mkdir($nsDir . '/en', 0777, true);
    file_put_contents($nsDir . '/en/greetings.php', '<?php return ["hi" => "Hi from namespace!"];');

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $loader->addNamespace('mypkg', $nsDir);
        $result = $loader->load('en', 'greetings', 'mypkg');

        expect($result)->toBe(['hi' => 'Hi from namespace!']);
    } finally {
        pathValidationCleanupDir($tmpDir);
        pathValidationCleanupDir($nsDir);
    }
});

it('throws TranslationException with a helpful suggestion when a segment is invalid', function (): void {
    $tmpDir = pathValidationCreateTempDir();
    $exception = null;

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $loader->load('../etc', 'messages');
    } catch (TranslationException $e) {
        $exception = $e;
    } finally {
        pathValidationCleanupDir($tmpDir);
    }

    expect($exception)
        ->toBeInstanceOf(TranslationException::class)
        ->and($exception->getMessage())->toContain('../etc')
        ->and($exception->getSuggestion())->toContain('A-Za-z0-9_-');
});

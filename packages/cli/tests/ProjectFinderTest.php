<?php

declare(strict_types=1);

use Marko\Cli\ProjectFinder;

function removeDirectory(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_link($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function createTempDir(): string
{
    return sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
}

it('finds project root when in project directory', function () {
    $tempDir = createTempDir();
    mkdir($tempDir . '/vendor/marko/core', 0755, true);

    try {
        $finder = new ProjectFinder();
        $result = $finder->find($tempDir);

        expect($result)->toBe(realpath($tempDir));
    } finally {
        removeDirectory($tempDir);
    }
});

it('finds project root when in subdirectory of project', function () {
    $tempDir = createTempDir();
    mkdir($tempDir . '/vendor/marko/core', 0755, true);
    mkdir($tempDir . '/src/deep/nested', 0755, true);

    try {
        $finder = new ProjectFinder();
        $result = $finder->find($tempDir . '/src/deep/nested');

        expect($result)->toBe(realpath($tempDir));
    } finally {
        removeDirectory($tempDir);
    }
});

it('returns null when not in a Marko project', function () {
    $tempDir = createTempDir();
    // Create a directory without vendor/marko/core
    mkdir($tempDir . '/some/random/dir', 0755, true);

    try {
        $finder = new ProjectFinder();
        $result = $finder->find($tempDir . '/some/random/dir');

        expect($result)->toBeNull();
    } finally {
        removeDirectory($tempDir);
    }
});

it('stops at filesystem root without infinite loop', function () {
    // Start from filesystem root - should not infinite loop
    $finder = new ProjectFinder();
    $result = $finder->find('/');

    expect($result)->toBeNull();
});

it('returns absolute path to project root', function () {
    $tempDir = createTempDir();
    mkdir($tempDir . '/vendor/marko/core', 0755, true);
    mkdir($tempDir . '/src', 0755, true);

    try {
        $finder = new ProjectFinder();
        // Use relative-style path with .. components
        $result = $finder->find($tempDir . '/src/../src');

        // Result should be an absolute path (starts with /)
        expect($result)->toStartWith('/')
            ->and($result)->not->toContain('..')
            ->and($result)->toBe(realpath($tempDir));
    } finally {
        removeDirectory($tempDir);
    }
});

it('detects project by presence of vendor/marko/core', function () {
    $tempDir = createTempDir();
    // Create vendor directory but NOT vendor/marko/core
    mkdir($tempDir . '/vendor/other/package', 0755, true);

    try {
        $finder = new ProjectFinder();
        $result = $finder->find($tempDir);

        // Should return null - vendor exists but not vendor/marko/core
        expect($result)->toBeNull();
    } finally {
        removeDirectory($tempDir);
    }
});

it('handles symlinked directories correctly', function () {
    $tempDir = createTempDir();
    // Create the real project directory
    mkdir($tempDir . '/real-project/vendor/marko/core', 0755, true);
    mkdir($tempDir . '/real-project/src', 0755, true);

    // Create a symlink to the project
    mkdir($tempDir . '/links', 0755, true);
    symlink($tempDir . '/real-project', $tempDir . '/links/project');

    try {
        $finder = new ProjectFinder();
        $result = $finder->find($tempDir . '/links/project/src');

        // Should resolve to the real path
        expect($result)->toBe(realpath($tempDir . '/real-project'));
    } finally {
        removeDirectory($tempDir);
    }
});

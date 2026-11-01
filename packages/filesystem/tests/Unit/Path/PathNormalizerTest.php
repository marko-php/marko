<?php

declare(strict_types=1);

use Marko\Filesystem\Exceptions\PathException;
use Marko\Filesystem\Path\BackslashPolicy;
use Marko\Filesystem\Path\PathNormalizer;

it('drops empty and dot segments', function (string $path, string $expected): void {
    $normalizer = new PathNormalizer(BackslashPolicy::Reject);

    expect($normalizer->normalize($path))->toBe($expected);
})->with([
    'plain' => ['docs/readme.txt', 'docs/readme.txt'],
    'leading slash' => ['/docs/readme.txt', 'docs/readme.txt'],
    'trailing slash' => ['docs/', 'docs'],
    'double slash' => ['docs//readme.txt', 'docs/readme.txt'],
    'dot segment' => ['./docs/./readme.txt', 'docs/readme.txt'],
    'root slash' => ['/', ''],
    'empty' => ['', ''],
    'dot' => ['.', ''],
]);

it('rejects parent directory segments', function (string $path): void {
    $normalizer = new PathNormalizer(BackslashPolicy::Translate);

    expect(fn () => $normalizer->normalize($path))
        ->toThrow(PathException::class, 'Path traversal attempt detected');
})->with([
    'leading' => '../secret.txt',
    'middle' => 'docs/../../secret.txt',
    'bare' => '..',
    'backslash separated' => 'docs\\..\\secret.txt',
]);

it('rejects NUL bytes', function (BackslashPolicy $policy): void {
    $normalizer = new PathNormalizer($policy);

    expect(fn () => $normalizer->normalize("docs/file\0.txt"))
        ->toThrow(PathException::class, 'Invalid path');
})->with([BackslashPolicy::Translate, BackslashPolicy::Reject]);

it('translates backslashes into forward slashes under the Translate policy', function (): void {
    $normalizer = new PathNormalizer(BackslashPolicy::Translate);

    expect($normalizer->normalize('docs\\sub\\file.txt'))->toBe('docs/sub/file.txt');
});

it('rejects backslashes under the Reject policy', function (): void {
    $normalizer = new PathNormalizer(BackslashPolicy::Reject);

    expect(fn () => $normalizer->normalize('docs\\file.txt'))
        ->toThrow(PathException::class, 'Invalid path');
});

it('returns the normalized directory for deletion', function (): void {
    $normalizer = new PathNormalizer(BackslashPolicy::Reject);

    expect($normalizer->normalizeForDeletion('/uploads/2026/'))->toBe('uploads/2026');
});

it('refuses to delete the disk root', function (string $path): void {
    $normalizer = new PathNormalizer(BackslashPolicy::Reject);

    expect(fn () => $normalizer->normalizeForDeletion($path))
        ->toThrow(PathException::class, 'Refusing to delete the disk root');
})->with(['', '/', '.', './', '//']);

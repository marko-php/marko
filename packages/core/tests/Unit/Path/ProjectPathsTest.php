<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;

it('treats Unix, Windows drive and UNC paths as absolute', function (string $path): void {
    expect(ProjectPaths::isAbsolute($path))->toBeTrue();
})->with([
    'unix' => '/var/cache/views',
    'unix root' => '/',
    'windows drive with backslash' => 'C:\\cache\\views',
    'windows drive with forward slash' => 'C:/cache/views',
    'lowercase windows drive' => 'd:\\cache',
    'unc share' => '\\\\server\\share\\views',
]);

it('treats relative paths as not absolute', function (string $path): void {
    expect(ProjectPaths::isAbsolute($path))->toBeFalse();
})->with([
    'plain relative' => 'storage/views',
    'dot relative' => './storage/views',
    'parent relative' => '../views',
    'windows backslash relative' => 'storage\\views',
    'drive-relative without separator' => 'C:views',
    'empty string' => '',
]);

it('returns absolute paths unchanged from resolve', function (string $path): void {
    $paths = new ProjectPaths('/srv/app');

    expect($paths->resolve($path))->toBe($path);
})->with([
    'unix' => '/var/cache/views',
    'windows drive with backslash' => 'C:\\cache\\views',
    'windows drive with forward slash' => 'C:/cache/views',
    'unc share' => '\\\\server\\share\\views',
]);

it('joins relative paths onto the project base path', function (): void {
    $paths = new ProjectPaths('/srv/app');

    expect($paths->resolve('storage/views'))->toBe('/srv/app/storage/views');
});

<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\Local\Factory\LocalFilesystemFactory;

function localDiskBasePath(
    FilesystemInterface $filesystem,
): string {
    return (new ReflectionProperty($filesystem, 'basePath'))->getValue($filesystem);
}

function removeLocalFactoryTestDirectory(
    string $directory,
): void {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
}

it('resolves a relative disk root against the project root, not the working directory', function () {
    $projectRoot = sys_get_temp_dir() . '/marko-fs-factory-' . bin2hex(random_bytes(8));
    $workingDirectory = $projectRoot . '/public';
    mkdir($workingDirectory, 0755, true);
    $originalCwd = getcwd();
    chdir($workingDirectory);

    try {
        $factory = new LocalFilesystemFactory(new ProjectPaths($projectRoot));
        $filesystem = $factory->create(['path' => 'storage/app']);
        $filesystem->write('hello.txt', 'hi');

        expect(localDiskBasePath($filesystem))->toBe($projectRoot . '/storage/app')
            ->and(file_get_contents($projectRoot . '/storage/app/hello.txt'))->toBe('hi')
            ->and(file_exists($workingDirectory . '/storage'))->toBeFalse();
    } finally {
        chdir($originalCwd);
        removeLocalFactoryTestDirectory($projectRoot);
    }
});

it('uses an absolute disk root as given', function (string $root) {
    $factory = new LocalFilesystemFactory(new ProjectPaths('/project'));

    expect(localDiskBasePath($factory->create(['path' => $root])))->toBe($root);
})->with([
    'unix absolute' => '/var/storage/app',
    'windows drive with backslash' => 'C:\\storage\\app',
    'windows drive with forward slash' => 'D:/storage/app',
    'unc share' => '\\\\server\\share\\app',
]);

it('throws when the disk configuration has no path', function () {
    $factory = new LocalFilesystemFactory(new ProjectPaths('/project'));

    $factory->create([]);
})->throws(FilesystemException::class, 'Missing path in disk configuration');

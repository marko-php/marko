<?php

declare(strict_types=1);

use Marko\Filesystem\Contracts\DirectoryListingInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FileNotFoundException;
use Marko\Filesystem\Exceptions\PathException;
use Marko\Filesystem\Local\Filesystem\LocalFilesystem;
use Marko\Filesystem\Values\FileInfo;

function getTestBasePath(): string
{
    return sys_get_temp_dir() . '/marko-filesystem-test-' . bin2hex(random_bytes(8));
}

function cleanupTestPath(
    string $path,
): void {
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if (
            $item === '.'
            || $item === '..'
        ) {
            continue;
        }

        $itemPath = $path . '/' . $item;

        if (
            is_dir($itemPath)
            && !is_link($itemPath)
        ) {
            cleanupTestPath($itemPath);
        } else {
            unlink($itemPath);
        }
    }

    rmdir($path);
}

beforeEach(function () {
    $this->basePath = getTestBasePath();
    mkdir($this->basePath, 0755, true);
    $this->filesystem = new LocalFilesystem($this->basePath);
});

afterEach(function () {
    cleanupTestPath($this->basePath);
});

it('implements FilesystemInterface', function () {
    expect($this->filesystem)->toBeInstanceOf(FilesystemInterface::class);
});

// exists tests
it('returns false for non-existent file', function () {
    expect($this->filesystem->exists('missing.txt'))->toBeFalse();
});

it('returns true for existing file', function () {
    file_put_contents($this->basePath . '/file.txt', 'content');

    expect($this->filesystem->exists('file.txt'))->toBeTrue();
});

it('returns true for existing directory', function () {
    mkdir($this->basePath . '/subdir');

    expect($this->filesystem->exists('subdir'))->toBeTrue();
});

// isFile tests
it('identifies file correctly', function () {
    file_put_contents($this->basePath . '/file.txt', 'content');

    expect($this->filesystem->isFile('file.txt'))->toBeTrue();
});

it('returns false for directory on isFile', function () {
    mkdir($this->basePath . '/subdir');

    expect($this->filesystem->isFile('subdir'))->toBeFalse();
});

// isDirectory tests
it('identifies directory correctly', function () {
    mkdir($this->basePath . '/subdir');

    expect($this->filesystem->isDirectory('subdir'))->toBeTrue();
});

it('returns false for file on isDirectory', function () {
    file_put_contents($this->basePath . '/file.txt', 'content');

    expect($this->filesystem->isDirectory('file.txt'))->toBeFalse();
});

// read tests
it('reads file contents', function () {
    file_put_contents($this->basePath . '/file.txt', 'Hello World');

    expect($this->filesystem->read('file.txt'))->toBe('Hello World');
});

it('throws exception when reading missing file', function () {
    $this->filesystem->read('missing.txt');
})->throws(FileNotFoundException::class);

// write tests
it('writes file contents', function () {
    $this->filesystem->write('file.txt', 'Hello World');

    expect(file_get_contents($this->basePath . '/file.txt'))->toBe('Hello World');
});

it('creates directory when writing file', function () {
    $this->filesystem->write('subdir/file.txt', 'content');

    expect(is_dir($this->basePath . '/subdir'))->toBeTrue()
        ->and(file_get_contents($this->basePath . '/subdir/file.txt'))->toBe('content');
});

it('returns true when writing successfully', function () {
    expect($this->filesystem->write('file.txt', 'content'))->toBeTrue();
});

// append tests
it('appends to file', function () {
    file_put_contents($this->basePath . '/file.txt', 'Hello');
    $this->filesystem->append('file.txt', ' World');

    expect(file_get_contents($this->basePath . '/file.txt'))->toBe('Hello World');
});

it('creates file when appending to non-existent', function () {
    $this->filesystem->append('new.txt', 'content');

    expect(file_get_contents($this->basePath . '/new.txt'))->toBe('content');
});

// delete tests
it('deletes existing file', function () {
    file_put_contents($this->basePath . '/file.txt', 'content');

    $this->filesystem->delete('file.txt');

    expect(file_exists($this->basePath . '/file.txt'))->toBeFalse();
});

it('returns true when deleting missing file', function () {
    expect($this->filesystem->delete('missing.txt'))->toBeTrue();
});

// copy tests
it('copies file', function () {
    file_put_contents($this->basePath . '/source.txt', 'content');

    $this->filesystem->copy('source.txt', 'dest.txt');

    expect(file_get_contents($this->basePath . '/dest.txt'))->toBe('content')
        ->and(file_exists($this->basePath . '/source.txt'))->toBeTrue();
});

it('throws when copying missing file', function () {
    $this->filesystem->copy('missing.txt', 'dest.txt');
})->throws(FileNotFoundException::class);

// move tests
it('moves file', function () {
    file_put_contents($this->basePath . '/source.txt', 'content');

    $this->filesystem->move('source.txt', 'dest.txt');

    expect(file_get_contents($this->basePath . '/dest.txt'))->toBe('content')
        ->and(file_exists($this->basePath . '/source.txt'))->toBeFalse();
});

it('throws when moving missing file', function () {
    $this->filesystem->move('missing.txt', 'dest.txt');
})->throws(FileNotFoundException::class);

// size tests
it('returns file size', function () {
    file_put_contents($this->basePath . '/file.txt', 'Hello World');

    expect($this->filesystem->size('file.txt'))->toBe(11);
});

it('throws when getting size of missing file', function () {
    $this->filesystem->size('missing.txt');
})->throws(FileNotFoundException::class);

// lastModified tests
it('returns last modified time', function () {
    file_put_contents($this->basePath . '/file.txt', 'content');
    $expected = filemtime($this->basePath . '/file.txt');

    expect($this->filesystem->lastModified('file.txt'))->toBe($expected);
});

// mimeType tests
it('returns mime type', function () {
    file_put_contents($this->basePath . '/file.txt', 'Hello World');

    expect($this->filesystem->mimeType('file.txt'))->toBe('text/plain');
});

// info tests
it('returns file info', function () {
    file_put_contents($this->basePath . '/file.txt', 'Hello World');

    $info = $this->filesystem->info('file.txt');

    expect($info)->toBeInstanceOf(FileInfo::class)
        ->and($info->path)->toBe('file.txt')
        ->and($info->size)->toBe(11)
        ->and($info->isDirectory)->toBeFalse();
});

it('throws when getting info of missing file', function () {
    $this->filesystem->info('missing.txt');
})->throws(FileNotFoundException::class);

// listDirectory tests
it('lists directory contents', function () {
    file_put_contents($this->basePath . '/file1.txt', 'content');
    file_put_contents($this->basePath . '/file2.txt', 'content');
    mkdir($this->basePath . '/subdir');

    $listing = $this->filesystem->listDirectory();

    expect($listing)->toBeInstanceOf(DirectoryListingInterface::class)
        ->and($listing->entries())->toHaveCount(3);
});

it('filters files in directory listing', function () {
    file_put_contents($this->basePath . '/file1.txt', 'content');
    mkdir($this->basePath . '/subdir');

    $listing = $this->filesystem->listDirectory();

    expect($listing->files())->toHaveCount(1)
        ->and($listing->files()[0]->path)->toBe('file1.txt');
});

it('filters directories in directory listing', function () {
    file_put_contents($this->basePath . '/file1.txt', 'content');
    mkdir($this->basePath . '/subdir');

    $listing = $this->filesystem->listDirectory();

    expect($listing->directories())->toHaveCount(1)
        ->and($listing->directories()[0]->path)->toBe('subdir');
});

// makeDirectory tests
it('creates directory', function () {
    $this->filesystem->makeDirectory('newdir');

    expect(is_dir($this->basePath . '/newdir'))->toBeTrue();
});

it('creates nested directories', function () {
    $this->filesystem->makeDirectory('level1/level2/level3');

    expect(is_dir($this->basePath . '/level1/level2/level3'))->toBeTrue();
});

it('returns true if directory already exists', function () {
    mkdir($this->basePath . '/existing');

    expect($this->filesystem->makeDirectory('existing'))->toBeTrue();
});

// deleteDirectory tests
it('deletes empty directory', function () {
    mkdir($this->basePath . '/empty');

    $this->filesystem->deleteDirectory('empty');

    expect(is_dir($this->basePath . '/empty'))->toBeFalse();
});

it('deletes directory with contents', function () {
    mkdir($this->basePath . '/full');
    file_put_contents($this->basePath . '/full/file.txt', 'content');
    mkdir($this->basePath . '/full/subdir');
    file_put_contents($this->basePath . '/full/subdir/nested.txt', 'content');

    $this->filesystem->deleteDirectory('full');

    expect(is_dir($this->basePath . '/full'))->toBeFalse();
});

it('removes a symlinked directory as a link without deleting the outside target contents', function () {
    $outside = getTestBasePath();
    mkdir($outside . '/victim', 0755, true);
    file_put_contents($outside . '/victim/secret.txt', 'secret');

    mkdir($this->basePath . '/dir');
    file_put_contents($this->basePath . '/dir/file.txt', 'content');
    symlink($outside . '/victim', $this->basePath . '/dir/link');

    try {
        $this->filesystem->deleteDirectory('dir');

        expect(is_dir($this->basePath . '/dir'))->toBeFalse()
            ->and(is_link($this->basePath . '/dir/link'))->toBeFalse()
            ->and(file_get_contents($outside . '/victim/secret.txt'))->toBe('secret');
    } finally {
        cleanupTestPath($outside);
    }
});

it('removes a directory symlink passed directly as a link without recursing into the target', function () {
    $outside = getTestBasePath();
    mkdir($outside, 0755, true);
    file_put_contents($outside . '/secret.txt', 'secret');

    try {
        mkdir($this->basePath . '/inner');
        file_put_contents($this->basePath . '/inner/keep.txt', 'keep');
        symlink($this->basePath . '/inner', $this->basePath . '/link');

        $this->filesystem->deleteDirectory('link');

        expect(is_link($this->basePath . '/link'))->toBeFalse()
            ->and(file_get_contents($this->basePath . '/inner/keep.txt'))->toBe('keep')
            ->and(file_get_contents($outside . '/secret.txt'))->toBe('secret');
    } finally {
        cleanupTestPath($outside);
    }
});

it('refuses to delete the disk root', function (string $path) {
    file_put_contents($this->basePath . '/keep.txt', 'keep');

    try {
        $this->filesystem->deleteDirectory($path);
        $this->fail("deleteDirectory('$path') should have thrown");
    } catch (PathException) {
        expect(file_get_contents($this->basePath . '/keep.txt'))->toBe('keep')
            ->and(is_dir($this->basePath))->toBeTrue();
    }
})->with(['', '/', '.', './', 'x/..', 'x/../', '/./.']);

it('refuses to read through a symlink that resolves outside the disk root', function () {
    $outside = getTestBasePath();
    mkdir($outside, 0755, true);
    file_put_contents($outside . '/secret.txt', 'secret');
    symlink($outside . '/secret.txt', $this->basePath . '/leak.txt');

    try {
        expect(fn () => $this->filesystem->read('leak.txt'))->toThrow(PathException::class);
    } finally {
        cleanupTestPath($outside);
    }
});

it('refuses to write into a symlinked directory that resolves outside the disk root', function () {
    $outside = getTestBasePath();
    mkdir($outside, 0755, true);
    symlink($outside, $this->basePath . '/escape');

    try {
        expect(fn () => $this->filesystem->write('escape/new/file.txt', 'pwned'))->toThrow(PathException::class)
            ->and(file_exists($outside . '/new/file.txt'))->toBeFalse();
    } finally {
        cleanupTestPath($outside);
    }
});

it('refuses to copy or move into a symlinked directory that resolves outside the disk root', function () {
    $outside = getTestBasePath();
    mkdir($outside, 0755, true);
    symlink($outside, $this->basePath . '/escape');
    file_put_contents($this->basePath . '/file.txt', 'content');

    try {
        expect(fn () => $this->filesystem->copy('file.txt', 'escape/copy.txt'))->toThrow(PathException::class)
            ->and(fn () => $this->filesystem->move('file.txt', 'escape/moved.txt'))->toThrow(PathException::class)
            ->and(file_exists($outside . '/copy.txt'))->toBeFalse()
            ->and(file_exists($outside . '/moved.txt'))->toBeFalse();
    } finally {
        cleanupTestPath($outside);
    }
});

it('writes to new nested paths inside a disk root that is itself a symlink', function () {
    $link = getTestBasePath();
    symlink($this->basePath, $link);

    try {
        $filesystem = new LocalFilesystem($link);
        $filesystem->write('a/b/c.txt', 'content');

        expect(file_get_contents($this->basePath . '/a/b/c.txt'))->toBe('content')
            ->and($filesystem->read('a/b/c.txt'))->toBe('content');
    } finally {
        unlink($link);
    }
});

it('writes when the disk root does not exist yet', function () {
    $root = getTestBasePath();
    $filesystem = new LocalFilesystem($root);

    try {
        $filesystem->write('nested/file.txt', 'content');

        expect(file_get_contents($root . '/nested/file.txt'))->toBe('content');
    } finally {
        cleanupTestPath($root);
    }
});

it('prevents path traversal with a trailing parent segment', function () {
    $this->filesystem->read('subdir/..');
})->throws(PathException::class);

// visibility tests
it('sets public visibility', function () {
    file_put_contents($this->basePath . '/file.txt', 'content');

    $this->filesystem->setVisibility('file.txt', 'public');

    $perms = fileperms($this->basePath . '/file.txt') & 0777;
    expect($perms)->toBe(0644);
});

it('sets private visibility', function () {
    file_put_contents($this->basePath . '/file.txt', 'content');

    $this->filesystem->setVisibility('file.txt', 'private');

    $perms = fileperms($this->basePath . '/file.txt') & 0777;
    expect($perms)->toBe(0600);
});

it('returns visibility', function () {
    file_put_contents($this->basePath . '/file.txt', 'content');
    chmod($this->basePath . '/file.txt', 0644);

    expect($this->filesystem->visibility('file.txt'))->toBe('public');
});

// path security tests
it('prevents path traversal with double dot', function () {
    $this->filesystem->read('../etc/passwd');
})->throws(PathException::class);

it('prevents path traversal at start', function () {
    $this->filesystem->read('../../secret.txt');
})->throws(PathException::class);

it('prevents path traversal in middle', function () {
    $this->filesystem->read('subdir/../../../etc/passwd');
})->throws(PathException::class);

// stream tests
it('reads file as stream', function () {
    file_put_contents($this->basePath . '/file.txt', 'Hello World');

    $stream = $this->filesystem->readStream('file.txt');

    expect(is_resource($stream))->toBeTrue()
        ->and(stream_get_contents($stream))->toBe('Hello World');

    fclose($stream);
});

it('writes from stream', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'Hello World');
    rewind($stream);

    $this->filesystem->writeStream('file.txt', $stream);

    fclose($stream);

    expect(file_get_contents($this->basePath . '/file.txt'))->toBe('Hello World');
});

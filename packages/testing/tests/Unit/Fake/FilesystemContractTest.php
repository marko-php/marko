<?php

declare(strict_types=1);

/*
 * One contract suite, run against FakeFilesystem and LocalFilesystem, so the fake behaves like a
 * real driver: same exceptions, same path rules, same root-deletion guard, same visibility,
 * stream and directory-listing behaviour.
 */

use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FileNotFoundException;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\Exceptions\PathException;
use Marko\Filesystem\Local\Filesystem\LocalFilesystem;
use Marko\Filesystem\Values\DirectoryEntry;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeFilesystem;

/**
 * Records every temporary disk root the suite creates, so afterEach can remove them.
 *
 * @return list<string>
 */
function filesystemContractRoots(
    ?string $add = null,
    bool $reset = false,
): array {
    static $roots = [];

    if ($add !== null) {
        $roots[] = $add;
    }

    $current = $roots;

    if ($reset) {
        $roots = [];
    }

    return $current;
}

function filesystemContractLocal(
    bool $public,
): LocalFilesystem {
    $root = sys_get_temp_dir() . '/marko-filesystem-contract-' . bin2hex(random_bytes(8));
    mkdir($root, 0755, true);
    filesystemContractRoots($root);

    return new LocalFilesystem($root, $public);
}

function filesystemContractRemove(
    string $path,
): void {
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
        filesystemContractRemove($path . '/' . $item);
    }

    rmdir($path);
}

dataset('filesystems', [
    'fake' => fn (): FilesystemInterface => new FakeFilesystem(clock: new FakeClock(), public: false),
    'local' => fn (): FilesystemInterface => filesystemContractLocal(false),
]);

dataset('public filesystems', [
    'fake' => fn (): FilesystemInterface => new FakeFilesystem(clock: new FakeClock(), public: true),
    'local' => fn (): FilesystemInterface => filesystemContractLocal(true),
]);

afterEach(function (): void {
    foreach (filesystemContractRoots(reset: true) as $root) {
        filesystemContractRemove($root);
    }
});

/**
 * @return list<array{string, bool, int}>
 */
function filesystemContractEntries(
    FilesystemInterface $filesystem,
    string $path,
): array {
    return array_map(
        fn (DirectoryEntry $entry): array => [$entry->path, $entry->isDirectory, $entry->size],
        $filesystem->listDirectory($path)->entries(),
    );
}

it('writes and reads a file, creating its parent directories', function (FilesystemInterface $filesystem): void {
    expect($filesystem->write('docs/2026/readme.txt', 'hello'))->toBeTrue()
        ->and($filesystem->read('docs/2026/readme.txt'))->toBe('hello')
        ->and($filesystem->exists('docs/2026/readme.txt'))->toBeTrue()
        ->and($filesystem->isFile('docs/2026/readme.txt'))->toBeTrue()
        ->and($filesystem->isDirectory('docs/2026/readme.txt'))->toBeFalse()
        ->and($filesystem->exists('docs/2026'))->toBeTrue()
        ->and($filesystem->isDirectory('docs'))->toBeTrue()
        ->and($filesystem->isFile('docs'))->toBeFalse();
})->with('filesystems');

it('overwrites an existing file', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'first');
    $filesystem->write('a.txt', 'second');

    expect($filesystem->read('a.txt'))->toBe('second');
})->with('filesystems');

it('reports a missing path as absent', function (FilesystemInterface $filesystem): void {
    expect($filesystem->exists('missing.txt'))->toBeFalse()
        ->and($filesystem->isFile('missing.txt'))->toBeFalse()
        ->and($filesystem->isDirectory('missing'))->toBeFalse();
})->with('filesystems');

it('treats the disk root as an existing directory', function (FilesystemInterface $filesystem): void {
    expect($filesystem->exists('/'))->toBeTrue()
        ->and($filesystem->isDirectory('/'))->toBeTrue()
        ->and($filesystem->isFile('/'))->toBeFalse();
})->with('filesystems');

it('throws FileNotFoundException for a missing file', function (
    FilesystemInterface $filesystem,
    Closure $operation,
): void {
    expect(fn () => $operation($filesystem))->toThrow(FileNotFoundException::class);
})->with('filesystems')->with([
    'read' => fn (FilesystemInterface $filesystem) => $filesystem->read('missing.txt'),
    'readStream' => fn (FilesystemInterface $filesystem) => $filesystem->readStream('missing.txt'),
    'info' => fn (FilesystemInterface $filesystem) => $filesystem->info('missing.txt'),
    'size' => fn (FilesystemInterface $filesystem) => $filesystem->size('missing.txt'),
    'lastModified' => fn (FilesystemInterface $filesystem) => $filesystem->lastModified('missing.txt'),
    'mimeType' => fn (FilesystemInterface $filesystem) => $filesystem->mimeType('missing.txt'),
    'copy' => fn (FilesystemInterface $filesystem) => $filesystem->copy('missing.txt', 'copy.txt'),
    'move' => fn (FilesystemInterface $filesystem) => $filesystem->move('missing.txt', 'moved.txt'),
    'setVisibility' => fn (FilesystemInterface $filesystem) => $filesystem->setVisibility('missing.txt', 'public'),
    'visibility' => fn (FilesystemInterface $filesystem) => $filesystem->visibility('missing.txt'),
    'listDirectory' => fn (FilesystemInterface $filesystem) => $filesystem->listDirectory('missing'),
]);

it('rejects path traversal', function (
    FilesystemInterface $filesystem,
    Closure $operation,
): void {
    expect(fn () => $operation($filesystem))
        ->toThrow(PathException::class, 'Path traversal attempt detected');
})->with('filesystems')->with([
    'read' => fn (FilesystemInterface $filesystem) => $filesystem->read('../secret.txt'),
    'write' => fn (FilesystemInterface $filesystem) => $filesystem->write('docs/../../secret.txt', 'x'),
    'exists' => fn (FilesystemInterface $filesystem) => $filesystem->exists('..'),
    'delete' => fn (FilesystemInterface $filesystem) => $filesystem->delete('../secret.txt'),
    'copy destination' => fn (FilesystemInterface $filesystem) => $filesystem->copy('a.txt', '../b.txt'),
    'listDirectory' => fn (FilesystemInterface $filesystem) => $filesystem->listDirectory('../'),
    'deleteDirectory' => fn (FilesystemInterface $filesystem) => $filesystem->deleteDirectory('docs/../..'),
]);

it('rejects a path containing a NUL byte', function (FilesystemInterface $filesystem): void {
    expect(fn () => $filesystem->write("docs/file\0.txt", 'x'))
        ->toThrow(PathException::class, 'Invalid path');
})->with('filesystems');

it('refuses to delete the disk root', function (FilesystemInterface $filesystem, string $root): void {
    $filesystem->write('keep.txt', 'still here');

    expect(fn () => $filesystem->deleteDirectory($root))
        ->toThrow(PathException::class, 'Refusing to delete the disk root')
        ->and($filesystem->read('keep.txt'))->toBe('still here');
})->with('filesystems')->with(['', '/', '.', './']);

it(
    'treats leading slashes, empty and dot segments as the same path',
    function (FilesystemInterface $filesystem): void {
        $filesystem->write('/docs//./readme.txt', 'hello');

        expect($filesystem->read('docs/readme.txt'))->toBe('hello')
            ->and($filesystem->exists('./docs/readme.txt'))->toBeTrue();
    },
)->with('filesystems');

it('gives new files the default private visibility', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'x');

    expect($filesystem->visibility('a.txt'))->toBe('private')
        ->and($filesystem->info('a.txt')->visibility)->toBe('private');
})->with('filesystems');

it('gives new files the default public visibility', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'x');

    expect($filesystem->visibility('a.txt'))->toBe('public');
})->with('public filesystems');

it('applies the visibility write option over the default', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'x', ['visibility' => 'public']);

    expect($filesystem->visibility('a.txt'))->toBe('public');
})->with('filesystems');

it('changes visibility with setVisibility', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'x');

    expect($filesystem->setVisibility('a.txt', 'public'))->toBeTrue()
        ->and($filesystem->visibility('a.txt'))->toBe('public');

    $filesystem->setVisibility('a.txt', 'private');

    expect($filesystem->visibility('a.txt'))->toBe('private');
})->with('filesystems');

it('writes from and reads into streams', function (FilesystemInterface $filesystem): void {
    $input = fopen('php://memory', 'r+b');
    fwrite($input, 'streamed contents');
    rewind($input);

    expect($filesystem->writeStream('streams/a.txt', $input))->toBeTrue();
    fclose($input);

    $output = $filesystem->readStream('streams/a.txt');

    expect($output)->toBeResource()
        ->and(stream_get_contents($output))->toBe('streamed contents');
    fclose($output);
})->with('filesystems');

it('appends to an existing file and creates a missing one', function (FilesystemInterface $filesystem): void {
    $filesystem->append('logs/app.log', 'one');
    $filesystem->append('logs/app.log', 'two');

    expect($filesystem->read('logs/app.log'))->toBe('onetwo');
})->with('filesystems');

it('deletes a file and treats deleting a missing file as done', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'x');

    expect($filesystem->delete('a.txt'))->toBeTrue()
        ->and($filesystem->exists('a.txt'))->toBeFalse()
        ->and($filesystem->delete('a.txt'))->toBeTrue();
})->with('filesystems');

it('copies a file into a new directory and keeps the source', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'x');

    expect($filesystem->copy('a.txt', 'backup/a.txt'))->toBeTrue()
        ->and($filesystem->read('backup/a.txt'))->toBe('x')
        ->and($filesystem->read('a.txt'))->toBe('x');
})->with('filesystems');

it('moves a file into a new directory and removes the source', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'x');

    expect($filesystem->move('a.txt', 'archive/a.txt'))->toBeTrue()
        ->and($filesystem->read('archive/a.txt'))->toBe('x')
        ->and($filesystem->exists('a.txt'))->toBeFalse();
})->with('filesystems');

it('reports size, mime type and file info', function (FilesystemInterface $filesystem): void {
    $filesystem->write('notes/a.txt', 'plain text contents');

    $info = $filesystem->info('notes/a.txt');

    expect($filesystem->size('notes/a.txt'))->toBe(19)
        ->and($filesystem->mimeType('notes/a.txt'))->toBe('text/plain')
        ->and($filesystem->lastModified('notes/a.txt'))->toBeInt()
        ->and($info->path)->toBe('notes/a.txt')
        ->and($info->size)->toBe(19)
        ->and($info->mimeType)->toBe('text/plain')
        ->and($info->isDirectory)->toBeFalse();
})->with('filesystems');

it('detects the mime type from the contents', function (FilesystemInterface $filesystem): void {
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
    );
    $filesystem->write('image.bin', $png);

    expect($filesystem->mimeType('image.bin'))->toBe('image/png');
})->with('filesystems');

it('reports directory info', function (FilesystemInterface $filesystem): void {
    $filesystem->makeDirectory('uploads');

    $info = $filesystem->info('uploads');

    expect($info->isDirectory)->toBeTrue()
        ->and($info->size)->toBe(0)
        ->and($info->mimeType)->toBe('directory');
})->with('filesystems');

it('lists the direct children of a directory, sorted by name', function (FilesystemInterface $filesystem): void {
    $filesystem->write('docs/b.txt', 'bb');
    $filesystem->write('docs/a.txt', 'a');
    $filesystem->write('docs/nested/c.txt', 'ccc');
    $filesystem->makeDirectory('docs/empty');

    expect(filesystemContractEntries($filesystem, 'docs'))->toBe([
        ['docs/a.txt', false, 1],
        ['docs/b.txt', false, 2],
        ['docs/empty', true, 0],
        ['docs/nested', true, 0],
    ]);
})->with('filesystems');

it('lists the disk root', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'a');
    $filesystem->write('docs/b.txt', 'b');

    expect(filesystemContractEntries($filesystem, '/'))->toBe([
        ['a.txt', false, 1],
        ['docs', true, 0],
    ]);
})->with('filesystems');

it('refuses to list a file as a directory', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'a');

    expect(fn () => $filesystem->listDirectory('a.txt'))
        ->toThrow(FilesystemException::class, 'Path is not a directory');
})->with('filesystems');

it('makes nested directories and treats an existing one as done', function (FilesystemInterface $filesystem): void {
    expect($filesystem->makeDirectory('a/b/c'))->toBeTrue()
        ->and($filesystem->isDirectory('a/b/c'))->toBeTrue()
        ->and($filesystem->isDirectory('a/b'))->toBeTrue()
        ->and($filesystem->makeDirectory('a/b/c'))->toBeTrue()
        ->and(filesystemContractEntries($filesystem, 'a/b/c'))->toBe([]);
})->with('filesystems');

it('keeps a directory after its last file is deleted', function (FilesystemInterface $filesystem): void {
    $filesystem->write('docs/a.txt', 'a');
    $filesystem->delete('docs/a.txt');

    expect($filesystem->isDirectory('docs'))->toBeTrue()
        ->and(filesystemContractEntries($filesystem, 'docs'))->toBe([]);
})->with('filesystems');

it('deletes a directory with everything in it', function (FilesystemInterface $filesystem): void {
    $filesystem->write('docs/a.txt', 'a');
    $filesystem->write('docs/nested/b.txt', 'b');
    $filesystem->write('other.txt', 'o');

    expect($filesystem->deleteDirectory('docs'))->toBeTrue()
        ->and($filesystem->exists('docs'))->toBeFalse()
        ->and($filesystem->exists('docs/nested/b.txt'))->toBeFalse()
        ->and($filesystem->read('other.txt'))->toBe('o');
})->with('filesystems');

it('treats deleting a missing directory as done', function (FilesystemInterface $filesystem): void {
    expect($filesystem->deleteDirectory('missing'))->toBeTrue();
})->with('filesystems');

it('does not delete a file passed to deleteDirectory', function (FilesystemInterface $filesystem): void {
    $filesystem->write('a.txt', 'a');

    expect($filesystem->deleteDirectory('a.txt'))->toBeTrue()
        ->and($filesystem->read('a.txt'))->toBe('a');
})->with('filesystems');

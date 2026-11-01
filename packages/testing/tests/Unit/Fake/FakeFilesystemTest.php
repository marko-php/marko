<?php

declare(strict_types=1);

use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\Exceptions\PathException;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeFilesystem;

function fakeFilesystem(): FakeFilesystem
{
    return new FakeFilesystem(clock: new FakeClock('2026-10-31 12:00:00'), public: false);
}

it('implements FilesystemInterface', function (): void {
    expect(fakeFilesystem())->toBeInstanceOf(FilesystemInterface::class);
});

it('takes lastModified from the injected clock', function (): void {
    $clock = new FakeClock('2026-10-31 12:00:00');
    $filesystem = new FakeFilesystem(clock: $clock, public: false);

    $filesystem->write('a.txt', 'a');
    $clock->travel('+1 hour');
    $filesystem->write('b.txt', 'b');

    expect($filesystem->lastModified('a.txt'))->toBe(new DateTimeImmutable('2026-10-31 12:00:00')->getTimestamp())
        ->and($filesystem->lastModified('b.txt'))->toBe(new DateTimeImmutable('2026-10-31 13:00:00')->getTimestamp())
        ->and($filesystem->info('b.txt')->lastModified)->toBe($filesystem->lastModified('b.txt'));
});

it('rejects backslashes, the strictest driver rule, so tests catch paths S3 would refuse', function (): void {
    expect(fn () => fakeFilesystem()->write('docs\\a.txt', 'x'))
        ->toThrow(PathException::class, 'Invalid path');
});

it('refuses to write a file over a directory', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->makeDirectory('docs');

    expect(fn () => $filesystem->write('docs', 'x'))
        ->toThrow(FilesystemException::class, "Cannot write to 'docs'");
});

it('refuses to create a file or directory beneath a file', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->write('a.txt', 'x');

    expect(fn () => $filesystem->write('a.txt/b.txt', 'x'))
        ->toThrow(FilesystemException::class, "'a.txt' is a file")
        ->and(fn () => $filesystem->makeDirectory('a.txt/sub'))
        ->toThrow(FilesystemException::class, "'a.txt' is a file");
});

it('moves a directory with everything in it', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->write('docs/a.txt', 'a');
    $filesystem->write('docs/nested/b.txt', 'b');

    $filesystem->move('docs', 'archive/docs');

    expect($filesystem->read('archive/docs/a.txt'))->toBe('a')
        ->and($filesystem->read('archive/docs/nested/b.txt'))->toBe('b')
        ->and($filesystem->exists('docs'))->toBeFalse();
});

it('passes assertExists for a file or directory and fails for a missing path', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->write('avatars/1.png', 'png');

    $filesystem->assertExists('avatars/1.png');
    $filesystem->assertExists('/avatars');

    expect(fn () => $filesystem->assertExists('avatars/2.png'))
        ->toThrow(AssertionFailedException::class, "Expected 'avatars/2.png' to exist on the fake filesystem");
});

it('passes assertMissing for a missing path and fails for an existing one', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->write('avatars/1.png', 'png');

    $filesystem->assertMissing('avatars/2.png');

    expect(fn () => $filesystem->assertMissing('avatars/1.png'))
        ->toThrow(AssertionFailedException::class, "Expected 'avatars/1.png' to be missing");
});

it('passes assertContent for matching contents and fails otherwise', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->write('a.txt', 'expected');

    $filesystem->assertContent('a.txt', 'expected');

    expect(fn () => $filesystem->assertContent('a.txt', 'other'))
        ->toThrow(AssertionFailedException::class, "Expected 'a.txt' to contain")
        ->and(fn () => $filesystem->assertContent('missing.txt', 'x'))
        ->toThrow(AssertionFailedException::class, "Expected 'missing.txt' to exist");
});

it('passes assertDirectoryEmpty for an empty directory and fails otherwise', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->makeDirectory('empty');
    $filesystem->write('full/a.txt', 'a');

    $filesystem->assertDirectoryEmpty('empty');

    expect(fn () => $filesystem->assertDirectoryEmpty('full'))
        ->toThrow(AssertionFailedException::class, "Expected directory 'full' to be empty but it contains: full/a.txt")
        ->and(fn () => $filesystem->assertDirectoryEmpty('missing'))
        ->toThrow(AssertionFailedException::class, "Expected directory 'missing' to exist");
});

it('counts every call that stores contents for assertWrittenCount', function (): void {
    $filesystem = fakeFilesystem();
    $stream = fopen('php://memory', 'r+b');
    fwrite($stream, 's');
    rewind($stream);

    $filesystem->write('a.txt', 'a');
    $filesystem->writeStream('b.txt', $stream);
    $filesystem->append('c.txt', 'c');
    $filesystem->copy('a.txt', 'd.txt');
    $filesystem->move('d.txt', 'e.txt');
    fclose($stream);

    $filesystem->assertWrittenCount(5);

    expect($filesystem->written)->toBe(['a.txt', 'b.txt', 'c.txt', 'd.txt', 'e.txt'])
        ->and(fn () => $filesystem->assertWrittenCount(1))
        ->toThrow(AssertionFailedException::class, 'Expected 1 files written but got 5.');
});

it('does not count reads, deletes or directory changes as writes', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->write('a.txt', 'a');

    $filesystem->read('a.txt');
    $filesystem->makeDirectory('docs');
    $filesystem->setVisibility('a.txt', 'public');
    $filesystem->delete('a.txt');
    $filesystem->deleteDirectory('docs');

    expect($filesystem->written)->toBe(['a.txt']);
});

it('clears every file, directory and recorded write', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->write('docs/a.txt', 'a');
    $filesystem->makeDirectory('empty');

    $filesystem->clear();

    expect($filesystem->exists('docs'))->toBeFalse()
        ->and($filesystem->exists('empty'))->toBeFalse()
        ->and($filesystem->written)->toBe([])
        ->and($filesystem->listDirectory('/')->entries())->toBe([]);
});

it('normalizes paths given to assertions', function (): void {
    $filesystem = fakeFilesystem();
    $filesystem->write('docs/a.txt', 'a');

    $filesystem->assertExists('/docs//a.txt');
    $filesystem->assertContent('./docs/a.txt', 'a');

    expect(fn () => $filesystem->assertExists('../docs/a.txt'))->toThrow(PathException::class);
});

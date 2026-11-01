<?php

declare(strict_types=1);

use Marko\Filesystem\Local\Filesystem\LocalFilesystem;
use Marko\Routing\Exceptions\FileResponseException;
use Marko\Routing\File\FilesystemFileSource;
use Marko\Routing\File\LocalFileSource;
use Marko\Routing\File\ResourceChunkReader;

describe('LocalFileSource', function (): void {
    beforeEach(function (): void {
        $this->path = tempnam(sys_get_temp_dir(), 'local_source_') . '.json';
        file_put_contents($this->path, '{"hello":"world"}');
        touch($this->path, 1_700_000_000);
    });

    afterEach(function (): void {
        @unlink($this->path);
    });

    it('reports size, mtime, mime type, filename and path', function (): void {
        $source = new LocalFileSource($this->path);

        expect($source->size())->toBe(17)
            ->and($source->lastModified())->toBe(1_700_000_000)
            ->and($source->mimeType())->toBe('application/json')
            ->and($source->filename())->toBe(basename($this->path))
            ->and($source->path())->toBe($this->path);
    });

    it('streams the whole file or a slice', function (): void {
        $source = new LocalFileSource($this->path);

        expect(implode('', [...$source->stream()]))->toBe('{"hello":"world"}')
            ->and(implode('', [...$source->stream(2, 5)]))->toBe('hello');
    });

    it('throws loudly for a missing file', function (): void {
        expect(fn () => new LocalFileSource('/no/such/file.txt'))
            ->toThrow(FileResponseException::class, "File '/no/such/file.txt' does not exist");
    });

    it('throws loudly for a directory', function (): void {
        expect(fn () => new LocalFileSource(sys_get_temp_dir()))
            ->toThrow(FileResponseException::class, 'is not a regular file');
    });

    it('throws loudly for an unreadable file', function (): void {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('root can read any file');
        }

        chmod($this->path, 0o000);

        try {
            expect(fn () => new LocalFileSource($this->path))
                ->toThrow(FileResponseException::class, 'is not readable');
        } finally {
            chmod($this->path, 0o644);
        }
    });
});

describe('FilesystemFileSource', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir() . '/fs_source_' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        file_put_contents($this->directory . '/clip.txt', '0123456789');
        touch($this->directory . '/clip.txt', 1_700_000_000);
        $this->source = new FilesystemFileSource(new LocalFilesystem($this->directory), 'clip.txt');
    });

    afterEach(function (): void {
        @unlink($this->directory . '/clip.txt');
        @rmdir($this->directory);
    });

    it('reads metadata through the local filesystem driver', function (): void {
        expect($this->source->size())->toBe(10)
            ->and($this->source->lastModified())->toBe(1_700_000_000)
            ->and($this->source->mimeType())->toBe('text/plain')
            ->and($this->source->filename())->toBe('clip.txt');
    });

    it('streams the whole file or a slice through readStream()', function (): void {
        expect(implode('', [...$this->source->stream()]))->toBe('0123456789')
            ->and(implode('', [...$this->source->stream(3, 4)]))->toBe('3456')
            ->and(implode('', [...$this->source->stream(8)]))->toBe('89');
    });
});

describe('ResourceChunkReader', function (): void {
    it('reads a range in chunks and closes the handle', function (): void {
        $handle = fopen('php://memory', 'w+b');
        fwrite($handle, str_repeat('a', 70_000) . str_repeat('b', 70_000));
        rewind($handle);

        $chunks = [...new ResourceChunkReader()->read($handle, 69_990, 20)];

        expect(implode('', $chunks))->toBe(str_repeat('a', 10) . str_repeat('b', 10))
            ->and(is_resource($handle))->toBeFalse();
    });

    it('skips forward on a stream that cannot seek', function (): void {
        $process = proc_open([PHP_BINARY, '-r', 'echo "0123456789";'], [1 => ['pipe', 'w']], $pipes);

        $contents = implode('', [...new ResourceChunkReader()->read($pipes[1], 4, 3)]);
        proc_close($process);

        expect($contents)->toBe('456');
    });
});

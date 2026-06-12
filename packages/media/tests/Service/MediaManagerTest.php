<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Service;

use Marko\Filesystem\Contracts\DirectoryListingInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Values\DirectoryListing;
use Marko\Filesystem\Values\FileInfo;
use Marko\Media\Config\MediaConfig;
use Marko\Media\Contracts\MediaRepositoryInterface;
use Marko\Media\Entity\Media;
use Marko\Media\Exceptions\UploadException;
use Marko\Media\Service\MediaManager;
use Marko\Media\Value\UploadedFile;
use Marko\Testing\Fake\FakeConfigRepository;

function makeJpegContent(): string
{
    // Minimal valid JPEG header (SOI + APP0 JFIF marker) so finfo detects image/jpeg
    return "\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
}

function makeUploadedFile(
    string $name = 'photo.jpg',
    string $tmpPath = '',
    string $mimeType = 'image/jpeg',
    int $size = 1024,
    string $extension = 'jpg',
): UploadedFile {
    return new UploadedFile(
        name: $name,
        tmpPath: $tmpPath !== '' ? $tmpPath : createTempFile(makeJpegContent()),
        mimeType: $mimeType,
        size: $size,
        extension: $extension,
    );
}

function createTempFile(
    string $content = 'test data',
): string {
    $path = tempnam(sys_get_temp_dir(), 'marko_media_test_');
    file_put_contents($path, $content);

    return $path;
}

function makeMediaConfig(
    int $maxFileSize = 10485760,
    string $disk = 'local',
    array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    array $mimeExtensionMap = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/gif' => ['gif'], 'image/webp' => ['webp']],
): MediaConfig {
    $configRepo = new FakeConfigRepository([
        'media.disk' => $disk,
        'media.max_file_size' => $maxFileSize,
        'media.allowed_mime_types' => $allowedMimeTypes,
        'media.allowed_extensions' => $allowedExtensions,
        'media.url_prefix' => '/storage',
        'media.mime_extension_map' => $mimeExtensionMap,
    ]);

    return new MediaConfig($configRepo);
}

function createJpegTempFile(): string
{
    $path = tempnam(sys_get_temp_dir(), 'marko_media_jpeg_');
    // Minimal valid JPEG header (SOI + APP0 JFIF marker)
    $jpeg = "\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
    file_put_contents($path, $jpeg);

    return $path;
}

function makeFilesystem(): FilesystemInterface
{
    return new class () implements FilesystemInterface
    {
        /** @var array<string, string> */
        public array $written = [];

        /** @var array<string> */
        public array $deleted = [];

        public function exists(
            string $path,
        ): bool {
            return isset($this->written[$path]);
        }

        public function isFile(
            string $path,
        ): bool {
            return isset($this->written[$path]);
        }

        public function isDirectory(
            string $path,
        ): bool {
            return false;
        }

        public function info(
            string $path,
        ): FileInfo {
            return new FileInfo(
                path: $path,
                size: strlen($this->written[$path] ?? ''),
                lastModified: time(),
                mimeType: 'application/octet-stream',
                isDirectory: false,
                visibility: 'public',
            );
        }

        public function read(
            string $path,
        ): string {
            return $this->written[$path] ?? '';
        }

        public function readStream(
            string $path,
        ): mixed {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $this->written[$path] ?? '');
            rewind($stream);

            return $stream;
        }

        public function write(
            string $path,
            string $contents,
            array $options = [],
        ): bool {
            $this->written[$path] = $contents;

            return true;
        }

        public function writeStream(
            string $path,
            mixed $resource,
            array $options = [],
        ): bool {
            $this->written[$path] = stream_get_contents($resource);

            return true;
        }

        public function append(
            string $path,
            string $contents,
        ): bool {
            $this->written[$path] = ($this->written[$path] ?? '') . $contents;

            return true;
        }

        public function delete(
            string $path,
        ): bool {
            $this->deleted[] = $path;
            unset($this->written[$path]);

            return true;
        }

        public function copy(
            string $source,
            string $destination,
        ): bool {
            $this->written[$destination] = $this->written[$source] ?? '';

            return true;
        }

        public function move(
            string $source,
            string $destination,
        ): bool {
            $this->written[$destination] = $this->written[$source] ?? '';
            unset($this->written[$source]);

            return true;
        }

        public function size(
            string $path,
        ): int {
            return strlen($this->written[$path] ?? '');
        }

        public function lastModified(
            string $path,
        ): int {
            return time();
        }

        public function mimeType(
            string $path,
        ): string {
            return 'application/octet-stream';
        }

        public function listDirectory(
            string $path = '/',
        ): DirectoryListingInterface {
            return new DirectoryListing([]);
        }

        public function makeDirectory(
            string $path,
        ): bool {
            return true;
        }

        public function deleteDirectory(
            string $path,
        ): bool {
            return true;
        }

        public function setVisibility(
            string $path,
            string $visibility,
        ): bool {
            return true;
        }

        public function visibility(
            string $path,
        ): string {
            return 'public';
        }
    };
}

function makeRepository(): MediaRepositoryInterface
{
    return new class () implements MediaRepositoryInterface
    {
        /** @var array<int, Media> */
        public array $saved = [];

        /** @var array<int> */
        public array $deleted = [];

        private int $nextId = 1;

        public function save(
            Media $media,
        ): Media {
            $media->id = $this->nextId++;
            $this->saved[$media->id] = $media;

            return $media;
        }

        public function delete(
            int $id,
        ): void {
            $this->deleted[] = $id;
            unset($this->saved[$id]);
        }

        public function find(
            int $id,
        ): ?Media {
            return $this->saved[$id] ?? null;
        }
    };
}

it('validates file size against configured maximum and throws UploadException', function (): void {
    $filesystem = makeFilesystem();
    $config = makeMediaConfig(maxFileSize: 1000);
    $repository = makeRepository();
    $file = makeUploadedFile(size: 2000);

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    expect(fn () => $manager->upload($file))
        ->toThrow(UploadException::class);
});

it('validates MIME type against configured whitelist and throws UploadException', function (): void {
    $filesystem = makeFilesystem();
    $config = makeMediaConfig(allowedMimeTypes: ['image/jpeg', 'image/png']);
    $repository = makeRepository();
    // File content is plain text — finfo detects text/plain, which is not in the allowlist
    $file = new UploadedFile(
        name: 'document.txt',
        tmpPath: createTempFile('plain text content'),
        mimeType: 'text/plain',
        size: 100,
        extension: 'txt',
    );

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    expect(fn () => $manager->upload($file))
        ->toThrow(UploadException::class);
});

it('validates file extension against configured whitelist and throws UploadException', function (): void {
    $filesystem = makeFilesystem();
    $config = makeMediaConfig(allowedExtensions: ['jpg', 'png']);
    $repository = makeRepository();
    $file = makeUploadedFile(mimeType: 'image/gif', extension: 'gif');

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    expect(fn () => $manager->upload($file))
        ->toThrow(UploadException::class);
});

it('creates Media entity record after successful upload', function (): void {
    $filesystem = makeFilesystem();
    $config = makeMediaConfig();
    $repository = makeRepository();
    $file = makeUploadedFile(name: 'image.jpg', mimeType: 'image/jpeg', size: 2048, extension: 'jpg');

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    $media = $manager->upload($file);

    expect($media->id)->not->toBeNull()
        ->and($media->originalFilename)->toBe('image.jpg')
        ->and($media->mimeType)->toBe('image/jpeg')
        ->and($media->size)->toBe(2048)
        ->and($media->disk)->toBe('local')
        ->and($media->path)->not->toBeEmpty()
        ->and($repository->saved)->toHaveCount(1);
});

it('deletes file from storage and removes entity record on delete', function (): void {
    $filesystem = makeFilesystem();
    $config = makeMediaConfig();
    $repository = makeRepository();
    $file = makeUploadedFile();

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    $media = $manager->upload($file);
    $storedPath = $media->path;
    $mediaId = $media->id;

    expect($filesystem->written)->toHaveKey($storedPath);

    $manager->delete($media);

    expect($filesystem->written)->not->toHaveKey($storedPath)
        ->and($filesystem->deleted)->toContain($storedPath)
        ->and($repository->deleted)->toContain($mediaId);
});

it('retrieves file contents from storage via Media entity path and disk', function (): void {
    $filesystem = makeFilesystem();
    $config = makeMediaConfig();
    $repository = makeRepository();
    $jpegContent = makeJpegContent();
    $tmpPath = createTempFile($jpegContent);
    $file = makeUploadedFile(tmpPath: $tmpPath);

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    $media = $manager->upload($file);

    $contents = $manager->retrieve($media);

    expect($contents)->toBe($jpegContent);
});

it('uploads a file to the configured disk via filesystem interface', function (): void {
    $filesystem = makeFilesystem();
    $config = makeMediaConfig();
    $repository = makeRepository();
    $file = makeUploadedFile();

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    $media = $manager->upload($file);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($filesystem->written)->not->toBeEmpty();
});

it(
    'derives the MIME type from file content via finfo and ignores the caller-supplied mimeType during upload',
    function (): void {
        $filesystem = makeFilesystem();
        $config = makeMediaConfig();
        $repository = makeRepository();
        $tmpPath = createJpegTempFile();

        // Caller supplies wrong mimeType — but file content is JPEG
        $file = new UploadedFile(
            name: 'photo.jpg',
            tmpPath: $tmpPath,
            mimeType: 'image/png',
            size: 1024,
            extension: 'jpg',
        );

        $manager = new MediaManager(
            filesystem: $filesystem,
            config: $config,
            repository: $repository,
        );

        $media = $manager->upload($file);

        // The persisted MIME should reflect the content-derived type, not the caller-supplied one
        expect($media->mimeType)->toBe('image/jpeg');

        @unlink($tmpPath);
    },
);

it('rejects an upload loudly when the content-derived MIME type is not in the allowed list', function (): void {
    $filesystem = makeFilesystem();
    $config = makeMediaConfig(allowedMimeTypes: ['image/png', 'image/gif']);
    $repository = makeRepository();
    $tmpPath = createJpegTempFile();

    // Content is JPEG — finfo will derive image/jpeg, which is not in the allowlist
    $file = new UploadedFile(
        name: 'photo.jpg',
        tmpPath: $tmpPath,
        mimeType: 'image/png',
        size: 1024,
        extension: 'jpg',
    );

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    expect(fn () => $manager->upload($file))
        ->toThrow(UploadException::class, 'image/jpeg');

    @unlink($tmpPath);
});

it(
    'rejects an upload loudly when the content-derived MIME type does not match the declared file extension',
    function (): void {
        $filesystem = makeFilesystem();
        $config = makeMediaConfig();
        $repository = makeRepository();
        $tmpPath = createJpegTempFile();

        // Content is JPEG (image/jpeg), but extension declares it as PNG
        $file = new UploadedFile(
            name: 'photo.png',
            tmpPath: $tmpPath,
            mimeType: 'image/jpeg',
            size: 1024,
            extension: 'png',
        );

        $manager = new MediaManager(
            filesystem: $filesystem,
            config: $config,
            repository: $repository,
        );

        expect(fn () => $manager->upload($file))
            ->toThrow(UploadException::class, 'png');

        @unlink($tmpPath);
    },
);

it('accepts an upload whose content-derived MIME type is in the allowed list', function (): void {
    $filesystem = makeFilesystem();
    $config = makeMediaConfig();
    $repository = makeRepository();
    $tmpPath = createJpegTempFile();

    $file = new UploadedFile(
        name: 'photo.jpg',
        tmpPath: $tmpPath,
        mimeType: 'image/jpeg',
        size: 1024,
        extension: 'jpg',
    );

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    $media = $manager->upload($file);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->mimeType)->toBe('image/jpeg');

    @unlink($tmpPath);
});

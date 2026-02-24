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

function makeUploadedFile(
    string $name = 'photo.jpg',
    string $tmpPath = '',
    string $mimeType = 'image/jpeg',
    int $size = 1024,
    string $extension = 'jpg',
): UploadedFile {
    return new UploadedFile(
        name: $name,
        tmpPath: $tmpPath !== '' ? $tmpPath : createTempFile('JPEG data'),
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
): MediaConfig {
    $configRepo = new FakeConfigRepository([
        'media.disk' => $disk,
        'media.max_file_size' => $maxFileSize,
        'media.allowed_mime_types' => $allowedMimeTypes,
        'media.allowed_extensions' => $allowedExtensions,
        'media.url_prefix' => '/storage',
    ]);

    return new MediaConfig($configRepo);
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
    $file = makeUploadedFile(mimeType: 'application/pdf', extension: 'pdf');

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
    $tmpPath = createTempFile('JPEG image data');
    $file = makeUploadedFile(tmpPath: $tmpPath);

    $manager = new MediaManager(
        filesystem: $filesystem,
        config: $config,
        repository: $repository,
    );

    $media = $manager->upload($file);

    $contents = $manager->retrieve($media);

    expect($contents)->toBe('JPEG image data');
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

<?php

declare(strict_types=1);

use Aws\Result;
use GuzzleHttp\Psr7\Stream;
use Marko\Filesystem\Exceptions\FileNotFoundException;
use Marko\Filesystem\S3\Filesystem\S3Filesystem;
use Marko\Filesystem\S3\Tests\Support\MockS3Client;

it('reads file contents from S3 using GetObject', function () {
    $bodyStream = fopen('php://memory', 'r+');
    fwrite($bodyStream, 'Hello, S3!');
    rewind($bodyStream);
    $mockBody = new Stream($bodyStream);

    $client = MockS3Client::create([
        'getObject' => fn (array $args) => new Result(['Body' => $mockBody]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $contents = $filesystem->read('path/to/file.txt');

    expect($contents)->toBe('Hello, S3!')
        ->and($client->calls[0]['method'])->toBe('getObject')
        ->and($client->calls[0]['args']['Bucket'])->toBe('test-bucket')
        ->and($client->calls[0]['args']['Key'])->toBe('path/to/file.txt');
});

it('reads file contents with prefix applied', function () {
    $bodyStream = fopen('php://memory', 'r+');
    fwrite($bodyStream, 'prefixed content');
    rewind($bodyStream);
    $mockBody = new Stream($bodyStream);

    $client = MockS3Client::create([
        'getObject' => fn (array $args) => new Result(['Body' => $mockBody]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig('uploads'));
    $contents = $filesystem->read('file.txt');

    expect($contents)->toBe('prefixed content')
        ->and($client->calls[0]['args']['Key'])->toBe('uploads/file.txt');
});

it('returns a stream resource from readStream', function () {
    $bodyStream = fopen('php://memory', 'r+');
    fwrite($bodyStream, 'stream content');
    rewind($bodyStream);
    $mockBody = new Stream($bodyStream);

    $client = MockS3Client::create([
        'getObject' => fn (array $args) => new Result(['Body' => $mockBody]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $stream = $filesystem->readStream('file.txt');

    expect(is_resource($stream))->toBeTrue()
        ->and(stream_get_contents($stream))->toBe('stream content');
});

it('returns true for exists when object exists', function () {
    $client = MockS3Client::create([
        'headObject' => fn (array $args) => new Result([
            'ContentLength' => 100,
            'ContentType' => 'text/plain',
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->exists('existing-file.txt'))->toBeTrue();
});

it('returns false for exists when object does not exist', function () {
    $client = MockS3Client::create([
        'headObject' => MockS3Client::createException('NotFound', 404),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->exists('missing-file.txt'))->toBeFalse();
});

it('throws FileNotFoundException when reading non-existent file', function () {
    $client = MockS3Client::create([
        'getObject' => MockS3Client::createException('NoSuchKey', 404),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $filesystem->read('non-existent.txt');
})->throws(FileNotFoundException::class);

it('returns file info with size, lastModified, and mimeType from HeadObject', function () {
    $lastModified = new DateTimeImmutable('2024-01-15 10:30:00');

    $client = MockS3Client::create([
        'headObject' => fn (array $args) => new Result([
            'ContentLength' => 2048,
            'LastModified' => $lastModified,
            'ContentType' => 'application/pdf',
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $info = $filesystem->info('document.pdf');

    expect($info->path)->toBe('document.pdf')
        ->and($info->size)->toBe(2048)
        ->and($info->lastModified)->toBe($lastModified->getTimestamp())
        ->and($info->mimeType)->toBe('application/pdf')
        ->and($info->isDirectory)->toBeFalse();
});

it('returns size from HeadObject', function () {
    $client = MockS3Client::create([
        'headObject' => fn (array $args) => new Result([
            'ContentLength' => 1048576,
            'LastModified' => new DateTimeImmutable(),
            'ContentType' => 'application/zip',
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->size('large-file.zip'))->toBe(1048576);
});

it('returns lastModified from HeadObject', function () {
    $lastModified = new DateTimeImmutable('2024-06-20 14:00:00');

    $client = MockS3Client::create([
        'headObject' => fn (array $args) => new Result([
            'ContentLength' => 100,
            'LastModified' => $lastModified,
            'ContentType' => 'text/plain',
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->lastModified('file.txt'))->toBe($lastModified->getTimestamp());
});

it('returns mimeType from HeadObject', function () {
    $client = MockS3Client::create([
        'headObject' => fn (array $args) => new Result([
            'ContentLength' => 5000,
            'LastModified' => new DateTimeImmutable(),
            'ContentType' => 'image/png',
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->mimeType('image.png'))->toBe('image/png');
});

it('returns true for isFile when object exists and is not a directory marker', function () {
    $client = MockS3Client::create([
        'headObject' => fn (array $args) => new Result([
            'ContentLength' => 100,
            'ContentType' => 'text/plain',
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->isFile('file.txt'))->toBeTrue();
});

it('returns false for isFile when object does not exist', function () {
    $client = MockS3Client::create([
        'headObject' => MockS3Client::createException('NotFound', 404),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->isFile('missing.txt'))->toBeFalse();
});

it('returns true for isDirectory when prefix has objects', function () {
    $client = MockS3Client::create([
        'listObjectsV2' => fn (array $args) => new Result([
            'KeyCount' => 1,
            'Contents' => [['Key' => 'images/photo.jpg']],
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->isDirectory('images'))->toBeTrue();
});

it('returns false for isDirectory when prefix has no objects', function () {
    $client = MockS3Client::create([
        'listObjectsV2' => fn (array $args) => new Result([
            'KeyCount' => 0,
            'Contents' => [],
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->isDirectory('empty-dir'))->toBeFalse();
});

it('lists directory contents using ListObjectsV2 with delimiter', function () {
    $client = MockS3Client::create([
        'listObjectsV2' => fn (array $args) => new Result([
            'Contents' => [
                [
                    'Key' => 'documents/readme.txt',
                    'Size' => 1024,
                    'LastModified' => new DateTimeImmutable('2024-01-10'),
                ],
                [
                    'Key' => 'documents/notes.md',
                    'Size' => 512,
                    'LastModified' => new DateTimeImmutable('2024-02-15'),
                ],
            ],
            'CommonPrefixes' => [
                ['Prefix' => 'documents/images/'],
            ],
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $listing = $filesystem->listDirectory('documents');

    $entries = $listing->entries();
    expect($entries)->toHaveCount(3);

    $files = $listing->files();
    expect($files)->toHaveCount(2)
        ->and($files[0]->path)->toBe('documents/readme.txt')
        ->and($files[0]->size)->toBe(1024)
        ->and($files[0]->isDirectory)->toBeFalse()
        ->and($files[1]->path)->toBe('documents/notes.md')
        ->and($files[1]->size)->toBe(512);

    $directories = $listing->directories();
    expect($directories)->toHaveCount(1)
        ->and($directories[0]->path)->toBe('documents/images')
        ->and($directories[0]->isDirectory)->toBeTrue();
});

it('lists directory contents with prefix stripping', function () {
    $client = MockS3Client::create([
        'listObjectsV2' => fn (array $args) => new Result([
            'Contents' => [
                [
                    'Key' => 'uploads/docs/file.txt',
                    'Size' => 100,
                    'LastModified' => new DateTimeImmutable('2024-01-01'),
                ],
            ],
            'CommonPrefixes' => [],
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig('uploads'));
    $listing = $filesystem->listDirectory('docs');

    $files = $listing->files();
    expect($files)->toHaveCount(1)
        ->and($files[0]->path)->toBe('docs/file.txt');
});

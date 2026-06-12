<?php

declare(strict_types=1);

use Aws\Result;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\S3\Filesystem\S3Filesystem;
use Marko\Filesystem\S3\Tests\Support\MockS3Client;

it('lists entries at the prefixed root without a double-slash prefix', function () {
    $capturedArgs = [];

    $client = MockS3Client::create([
        'listObjectsV2' => function (array $args) use (&$capturedArgs): Result {
            $capturedArgs[] = $args;

            return new Result([
                'IsTruncated' => false,
                'Contents' => [
                    [
                        'Key' => 'uploads/file.txt',
                        'Size' => 100,
                        'LastModified' => new DateTimeImmutable('2024-01-01'),
                    ],
                ],
                'CommonPrefixes' => [],
            ]);
        },
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig('uploads'));
    $filesystem->listDirectory('/');

    expect($capturedArgs)->toHaveCount(1)
        ->and($capturedArgs[0]['Prefix'])->toBe('uploads/');
});

it('returns true and makes no delete call when the directory prefix is empty', function () {
    $client = MockS3Client::create([]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->deleteDirectory('/');

    $methods = array_column($client->calls, 'method');

    expect($result)->toBeTrue()
        ->and($methods)->not->toContain('deleteObjects')
        ->and($methods)->not->toContain('listObjectsV2');
});

it('throws a loud FilesystemException naming the keys when deleteObjects reports per-key errors', function () {
    $client = MockS3Client::create([
        'listObjectsV2' => fn (array $args): Result => new Result([
            'IsTruncated' => false,
            'Contents' => [
                ['Key' => 'dir/file1.txt'],
                ['Key' => 'dir/file2.txt'],
            ],
        ]),
        'deleteObjects' => fn (array $args): Result => new Result([
            'Deleted' => [],
            'Errors' => [
                ['Key' => 'dir/file1.txt', 'Code' => 'AccessDenied', 'Message' => 'Access Denied'],
                ['Key' => 'dir/file2.txt', 'Code' => 'InternalError', 'Message' => 'We encountered an internal error'],
            ],
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect(fn () => $filesystem->deleteDirectory('dir'))
        ->toThrow(
            FilesystemException::class,
            'dir/file1.txt',
        );
});

it('deletes more than one thousand objects by paging through continuation tokens', function () {
    $listCallCount = 0;
    $deleteCallArgs = [];

    $client = MockS3Client::create([
        'listObjectsV2' => function (array $args) use (&$listCallCount): Result {
            $listCallCount++;

            if ($listCallCount === 1) {
                $contents = array_map(
                    fn (int $i): array => ['Key' => "dir/file$i.txt"],
                    range(1, 1000),
                );

                return new Result([
                    'IsTruncated' => true,
                    'NextContinuationToken' => 'token-page-2',
                    'Contents' => $contents,
                ]);
            }

            return new Result([
                'IsTruncated' => false,
                'Contents' => [
                    ['Key' => 'dir/file1001.txt'],
                ],
            ]);
        },
        'deleteObjects' => function (array $args) use (&$deleteCallArgs): Result {
            $deleteCallArgs[] = $args;

            return new Result(['Deleted' => $args['Delete']['Objects'], 'Errors' => []]);
        },
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->deleteDirectory('dir');

    expect($result)->toBeTrue()
        ->and($listCallCount)->toBe(2)
        ->and($deleteCallArgs)->toHaveCount(2)
        ->and($deleteCallArgs[0]['Delete']['Objects'])->toHaveCount(1000)
        ->and($deleteCallArgs[1]['Delete']['Objects'])->toHaveCount(1);
});

it('aggregates common prefixes across multiple truncated listing pages', function () {
    $callCount = 0;

    $client = MockS3Client::create([
        'listObjectsV2' => function (array $args) use (&$callCount): Result {
            $callCount++;

            if ($callCount === 1) {
                return new Result([
                    'IsTruncated' => true,
                    'NextContinuationToken' => 'token-page-2',
                    'Contents' => [],
                    'CommonPrefixes' => [
                        ['Prefix' => 'docs/images/'],
                    ],
                ]);
            }

            return new Result([
                'IsTruncated' => false,
                'Contents' => [],
                'CommonPrefixes' => [
                    ['Prefix' => 'docs/videos/'],
                ],
            ]);
        },
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $listing = $filesystem->listDirectory('docs');

    $directories = $listing->directories();

    expect($callCount)->toBe(2)
        ->and($directories)->toHaveCount(2)
        ->and($directories[0]->path)->toBe('docs/images')
        ->and($directories[1]->path)->toBe('docs/videos');
});

it('follows the continuation token to return objects beyond the first listing page', function () {
    $callCount = 0;

    $client = MockS3Client::create([
        'listObjectsV2' => function (array $args) use (&$callCount): Result {
            $callCount++;

            if ($callCount === 1) {
                return new Result([
                    'IsTruncated' => true,
                    'NextContinuationToken' => 'token-page-2',
                    'Contents' => [
                        [
                            'Key' => 'docs/file1.txt',
                            'Size' => 100,
                            'LastModified' => new DateTimeImmutable('2024-01-01'),
                        ],
                    ],
                    'CommonPrefixes' => [],
                ]);
            }

            return new Result([
                'IsTruncated' => false,
                'Contents' => [
                    [
                        'Key' => 'docs/file2.txt',
                        'Size' => 200,
                        'LastModified' => new DateTimeImmutable('2024-01-02'),
                    ],
                ],
                'CommonPrefixes' => [],
            ]);
        },
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $listing = $filesystem->listDirectory('docs');

    $files = $listing->files();

    expect($callCount)->toBe(2)
        ->and($files)->toHaveCount(2)
        ->and($files[0]->path)->toBe('docs/file1.txt')
        ->and($files[1]->path)->toBe('docs/file2.txt');
});

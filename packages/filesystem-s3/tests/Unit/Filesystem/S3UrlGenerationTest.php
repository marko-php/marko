<?php

declare(strict_types=1);

use Aws\CommandInterface;
use GuzzleHttp\Psr7\Request;
use Marko\Filesystem\S3\Config\S3Config;
use Marko\Filesystem\S3\Filesystem\S3Filesystem;
use Marko\Filesystem\S3\Tests\Support\MockS3Client;

it('generates public URL using default S3 endpoint pattern', function () {
    $client = MockS3Client::create();

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $url = $filesystem->url('images/photo.jpg');

    expect($url)->toBe('https://test-bucket.s3.us-east-1.amazonaws.com/images/photo.jpg');
});

it('generates public URL using custom base URL from config', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-east-1',
        key: 'key',
        secret: 'secret',
        url: 'https://cdn.example.com',
    );

    $client = MockS3Client::create();

    $filesystem = new S3Filesystem($client, $config);
    $url = $filesystem->url('images/photo.jpg');

    expect($url)->toBe('https://cdn.example.com/images/photo.jpg');
});

it('includes prefix in generated URLs', function () {
    $client = MockS3Client::create();

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig('uploads'));
    $url = $filesystem->url('images/photo.jpg');

    expect($url)->toBe('https://test-bucket.s3.us-east-1.amazonaws.com/uploads/images/photo.jpg');
});

it('generates pre-signed temporary URL with default expiration', function () {
    $mockRequest = new Request(
        'GET',
        'https://test-bucket.s3.us-east-1.amazonaws.com/file.txt?X-Amz-Expires=3600&X-Amz-Signature=abc123'
    );

    $client = MockS3Client::create([
        'createPresignedRequest' => function (CommandInterface $command, $expires, array $options) use ($mockRequest) {
            return $mockRequest;
        },
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $url = $filesystem->temporaryUrl('file.txt');

    expect($url)->toBe(
        'https://test-bucket.s3.us-east-1.amazonaws.com/file.txt?X-Amz-Expires=3600&X-Amz-Signature=abc123'
    );

    // Verify getCommand was called
    $getCommandCall = null;

    foreach ($client->calls as $call) {
        if (str_starts_with($call['method'], 'getCommand:')) {
            $getCommandCall = $call;
        }
    }

    expect($getCommandCall)->not->toBeNull()
        ->and($getCommandCall['method'])->toBe('getCommand:GetObject')
        ->and($getCommandCall['args']['Bucket'])->toBe('test-bucket')
        ->and($getCommandCall['args']['Key'])->toBe('file.txt');
});

it('generates pre-signed temporary URL with custom expiration', function () {
    $mockRequest = new Request('GET', 'https://test-bucket.s3.us-east-1.amazonaws.com/file.txt?signed=true');

    $client = MockS3Client::create([
        'createPresignedRequest' => function (CommandInterface $command, $expires, array $options) use ($mockRequest) {
            // Verify the expiration time is approximately correct
            expect($expires)->toBeInstanceOf(DateTimeImmutable::class);

            return $mockRequest;
        },
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $url = $filesystem->temporaryUrl('file.txt', 7200);

    expect($url)->toBe('https://test-bucket.s3.us-east-1.amazonaws.com/file.txt?signed=true');
});

it('uses custom endpoint for URL generation on S3-compatible services', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-east-1',
        key: 'minioadmin',
        secret: 'minioadmin',
        endpoint: 'http://minio:9000',
        pathStyleEndpoint: true,
    );

    $client = MockS3Client::create();

    $filesystem = new S3Filesystem($client, $config);
    $url = $filesystem->url('images/photo.jpg');

    expect($url)->toBe('http://minio:9000/my-bucket/images/photo.jpg');
});

it('includes prefix in temporary URL generation', function () {
    $mockRequest = new Request('GET', 'https://test-bucket.s3.us-east-1.amazonaws.com/uploads/file.txt?signed=true');

    $client = MockS3Client::create([
        'createPresignedRequest' => fn (CommandInterface $command, $expires, array $options) => $mockRequest,
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig('uploads'));
    $url = $filesystem->temporaryUrl('file.txt');

    // Verify the getCommand was called with prefixed key
    $getCommandCall = null;

    foreach ($client->calls as $call) {
        if (str_starts_with($call['method'], 'getCommand:')) {
            $getCommandCall = $call;
        }
    }

    expect($getCommandCall)->not->toBeNull()
        ->and($getCommandCall['args']['Key'])->toBe('uploads/file.txt');
});

it('generates URL with custom base URL and prefix', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-east-1',
        key: 'key',
        secret: 'secret',
        prefix: 'media',
        url: 'https://cdn.example.com',
    );

    $client = MockS3Client::create();

    $filesystem = new S3Filesystem($client, $config);
    $url = $filesystem->url('images/photo.jpg');

    expect($url)->toBe('https://cdn.example.com/media/images/photo.jpg');
});

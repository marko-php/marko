<?php

declare(strict_types=1);

use Marko\Filesystem\S3\Config\S3Config;

it('creates S3Config with all required parameters', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-east-1',
        key: 'AKIAIOSFODNN7EXAMPLE',
        secret: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    );

    expect($config->bucket)->toBe('my-bucket')
        ->and($config->region)->toBe('us-east-1')
        ->and($config->key)->toBe('AKIAIOSFODNN7EXAMPLE')
        ->and($config->secret)->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
        ->and($config->prefix)->toBe('')
        ->and($config->endpoint)->toBeNull()
        ->and($config->url)->toBeNull()
        ->and($config->pathStyleEndpoint)->toBeFalse();
});

it('creates S3Config with optional endpoint for S3-compatible services', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-east-1',
        key: 'minioadmin',
        secret: 'minioadmin',
        endpoint: 'http://minio:9000',
        pathStyleEndpoint: true,
    );

    expect($config->endpoint)->toBe('http://minio:9000')
        ->and($config->pathStyleEndpoint)->toBeTrue();
});

it('normalizes prefix by trimming leading and trailing slashes', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-east-1',
        key: 'key',
        secret: 'secret',
        prefix: '/uploads/images/',
    );

    expect($config->prefix)->toBe('uploads/images');
});

it('allows empty prefix for bucket root', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-east-1',
        key: 'key',
        secret: 'secret',
        prefix: '',
    );

    expect($config->prefix)->toBe('');
});

it('stores path_style_endpoint flag for MinIO compatibility', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-east-1',
        key: 'key',
        secret: 'secret',
        pathStyleEndpoint: true,
    );

    expect($config->pathStyleEndpoint)->toBeTrue();
});

it('builds S3Client options array with correct credentials and region', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-west-2',
        key: 'AKIAIOSFODNN7EXAMPLE',
        secret: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    );

    $options = $config->toClientOptions();

    expect($options)->toHaveKey('region')
        ->and($options['region'])->toBe('us-west-2')
        ->and($options)->toHaveKey('version')
        ->and($options['version'])->toBe('latest')
        ->and($options)->toHaveKey('credentials')
        ->and($options['credentials']['key'])->toBe('AKIAIOSFODNN7EXAMPLE')
        ->and($options['credentials']['secret'])->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
        ->and($options)->not->toHaveKey('endpoint');
});

it('includes endpoint in client options when custom endpoint is set', function () {
    $config = new S3Config(
        bucket: 'my-bucket',
        region: 'us-east-1',
        key: 'minioadmin',
        secret: 'minioadmin',
        endpoint: 'http://minio:9000',
        pathStyleEndpoint: true,
    );

    $options = $config->toClientOptions();

    expect($options)->toHaveKey('endpoint')
        ->and($options['endpoint'])->toBe('http://minio:9000')
        ->and($options)->toHaveKey('use_path_style_endpoint')
        ->and($options['use_path_style_endpoint'])->toBeTrue();
});

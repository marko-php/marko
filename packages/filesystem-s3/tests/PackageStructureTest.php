<?php

declare(strict_types=1);

use Marko\Filesystem\Attributes\FilesystemDriver;
use Marko\Filesystem\Contracts\FilesystemDriverFactoryInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\S3\Factory\S3FilesystemFactory;

it('has valid composer.json with correct package name marko/filesystem-s3', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue()
        ->and(json_decode(file_get_contents($composerPath), true))->toBeArray()
        ->and(json_decode(file_get_contents($composerPath), true)['name'])->toBe('marko/filesystem-s3');
});

it('has correct description in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['description'])->toBe('S3 filesystem driver for Marko Framework');
});

it('has type marko-module in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module');
});

it('has MIT license in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['license'])->toBe('MIT');
});

it('requires PHP 8.5 or higher', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5');
});

it('requires aws/aws-sdk-php and marko/filesystem in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('marko/filesystem')
        ->and($composer['require'])->toHaveKey('aws/aws-sdk-php')
        ->and($composer['require']['aws/aws-sdk-php'])->toBe('^3.0');
});

it('has PSR-4 autoloading configured for Marko\\Filesystem\\S3 namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Filesystem\\S3\\')
        ->and($composer['autoload']['psr-4']['Marko\\Filesystem\\S3\\'])->toBe('src/');
});

it('has extra.marko.module set to true', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('has src directory for source code', function () {
    $srcPath = dirname(__DIR__) . '/src';

    expect(is_dir($srcPath))->toBeTrue();
});

it('has tests directory for tests', function () {
    $testsPath = dirname(__DIR__) . '/tests';

    expect(is_dir($testsPath))->toBeTrue();
});

it('has S3FilesystemFactory with FilesystemDriver attribute named s3', function () {
    $reflection = new ReflectionClass(S3FilesystemFactory::class);
    $attributes = $reflection->getAttributes(FilesystemDriver::class);

    expect($attributes)->toHaveCount(1);

    $attribute = $attributes[0]->newInstance();

    expect($attribute->name)->toBe('s3');
});

it('has S3FilesystemFactory implementing FilesystemDriverFactoryInterface', function () {
    $reflection = new ReflectionClass(S3FilesystemFactory::class);

    expect($reflection->implementsInterface(FilesystemDriverFactoryInterface::class))->toBeTrue();
});

it('creates S3Filesystem from config array with all required parameters', function () {
    // We cannot test with a real S3Client without credentials, but we can
    // verify the factory validates and processes the config correctly
    // by testing it throws for missing keys and passes for complete configs.
    $factory = new S3FilesystemFactory();

    // This will create a real S3Client which doesn't actually connect yet
    $filesystem = $factory->create([
        'bucket' => 'test-bucket',
        'region' => 'us-east-1',
        'key' => 'test-key',
        'secret' => 'test-secret',
    ]);

    expect($filesystem)->toBeInstanceOf(FilesystemInterface::class);
});

it('throws FilesystemException when required config keys are missing', function () {
    $factory = new S3FilesystemFactory();
    $factory->create([
        'region' => 'us-east-1',
        'key' => 'test-key',
        'secret' => 'test-secret',
        // Missing 'bucket'
    ]);
})->throws(FilesystemException::class, "Missing required S3 config key: 'bucket'");

it('redacts secret in error context when config validation fails', function () {
    $factory = new S3FilesystemFactory();

    try {
        $factory->create([
            'bucket' => 'my-bucket',
            'key' => 'my-key',
            'secret' => 'super-secret-value',
            // Missing 'region'
        ]);
    } catch (FilesystemException $e) {
        expect($e->getContext())->toContain('***REDACTED***')
            ->and($e->getContext())->not->toContain('super-secret-value');

        return;
    }

    $this->fail('Expected FilesystemException was not thrown');
});

it('passes optional config values to S3Config', function () {
    $factory = new S3FilesystemFactory();

    $filesystem = $factory->create([
        'bucket' => 'test-bucket',
        'region' => 'us-east-1',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'prefix' => 'uploads',
        'endpoint' => 'http://minio:9000',
        'url' => 'https://cdn.example.com',
        'path_style_endpoint' => true,
    ]);

    expect($filesystem)->toBeInstanceOf(FilesystemInterface::class);
});

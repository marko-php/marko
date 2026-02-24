<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Service;

use Marko\Media\Config\MediaConfig;
use Marko\Media\Entity\Media;
use Marko\Media\Service\UrlGenerator;
use Marko\Testing\Fake\FakeConfigRepository;

function makeUrlGeneratorConfig(
    string $urlPrefix = '/storage',
    string $disk = 'local',
): MediaConfig {
    $configRepo = new FakeConfigRepository([
        'media.disk' => $disk,
        'media.max_file_size' => 10485760,
        'media.allowed_mime_types' => ['image/jpeg'],
        'media.allowed_extensions' => ['jpg'],
        'media.url_prefix' => $urlPrefix,
    ]);

    return new MediaConfig($configRepo);
}

function makeMedia(
    string $path = '2024/01/abc123.jpg',
    string $disk = 'local',
): Media {
    $media = new Media();
    $media->id = 1;
    $media->filename = 'abc123.jpg';
    $media->originalFilename = 'photo.jpg';
    $media->mimeType = 'image/jpeg';
    $media->size = 1024;
    $media->disk = $disk;
    $media->path = $path;

    return $media;
}

it('generates public URL for media with configurable prefix', function (): void {
    $config = makeUrlGeneratorConfig(urlPrefix: '/storage');
    $generator = new UrlGenerator($config);
    $media = makeMedia(path: '2024/01/abc123.jpg');

    $url = $generator->url($media);

    expect($url)->toBe('/storage/2024/01/abc123.jpg');
});

it('generates URL based on the media disk configuration', function (): void {
    $config = makeUrlGeneratorConfig(urlPrefix: 'https://s3.amazonaws.com/bucket');
    $generator = new UrlGenerator($config);
    $media = makeMedia(path: '2024/01/abc123.jpg', disk: 's3');

    $url = $generator->url($media);

    expect($url)->toBe('https://s3.amazonaws.com/bucket/2024/01/abc123.jpg');
});

<?php

declare(strict_types=1);

namespace Marko\Filesystem\S3\Factory;

use Aws\S3\S3Client;
use Marko\Filesystem\Attributes\FilesystemDriver;
use Marko\Filesystem\Contracts\FilesystemDriverFactoryInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\S3\Config\S3Config;
use Marko\Filesystem\S3\Filesystem\S3Filesystem;

#[FilesystemDriver('s3')]
class S3FilesystemFactory implements FilesystemDriverFactoryInterface
{
    private const array REQUIRED_KEYS = ['bucket', 'region', 'key', 'secret'];

    /**
     * @param array<string, mixed> $config
     * @throws FilesystemException
     */
    public function create(
        array $config,
    ): FilesystemInterface {
        $this->validateConfig($config);

        $s3Config = new S3Config(
            bucket: (string) $config['bucket'],
            region: (string) $config['region'],
            key: (string) $config['key'],
            secret: (string) $config['secret'],
            prefix: (string) ($config['prefix'] ?? ''),
            endpoint: isset($config['endpoint']) ? (string) $config['endpoint'] : null,
            url: isset($config['url']) ? (string) $config['url'] : null,
            pathStyleEndpoint: (bool) ($config['path_style_endpoint'] ?? false),
        );

        $client = new S3Client($s3Config->toClientOptions());

        return new S3Filesystem($client, $s3Config);
    }

    /**
     * @param array<string, mixed> $config
     * @throws FilesystemException
     */
    private function validateConfig(
        array $config,
    ): void {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($config[$key]) || $config[$key] === '') {
                $redactedConfig = $config;

                if (isset($redactedConfig['secret'])) {
                    $redactedConfig['secret'] = '***REDACTED***';
                }

                throw new FilesystemException(
                    message: "Missing required S3 config key: '$key'",
                    context: 'Provided config: ' . json_encode($redactedConfig, JSON_THROW_ON_ERROR),
                    suggestion: "Add '$key' to your S3 disk configuration or set the corresponding environment variable",
                );
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Marko\Filesystem\S3\Config;

readonly class S3Config
{
    public string $prefix;

    public function __construct(
        public string $bucket,
        public string $region,
        public string $key,
        public string $secret,
        string $prefix = '',
        public ?string $endpoint = null,
        public ?string $url = null,
        public bool $pathStyleEndpoint = false,
    ) {
        $this->prefix = trim($prefix, '/');
    }

    /**
     * Build the options array suitable for creating an S3Client instance.
     *
     * @return array<string, mixed>
     */
    public function toClientOptions(): array
    {
        $options = [
            'region' => $this->region,
            'version' => 'latest',
            'credentials' => [
                'key' => $this->key,
                'secret' => $this->secret,
            ],
        ];

        if ($this->endpoint !== null) {
            $options['endpoint'] = $this->endpoint;
            $options['use_path_style_endpoint'] = $this->pathStyleEndpoint;
        }

        return $options;
    }
}

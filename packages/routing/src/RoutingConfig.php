<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\FileResponseException;
use Marko\Routing\Http\FileOffload;

readonly class RoutingConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * Base URL for absolute URLs; empty when none is configured.
     *
     * @throws ConfigNotFoundException
     */
    public function url(): string
    {
        return $this->config->getString('routing.url');
    }

    /**
     * The configured file offload, or null when routing.file_offload.header is empty.
     *
     * @throws ConfigNotFoundException|FileResponseException
     */
    public function fileOffload(): ?FileOffload
    {
        $header = $this->config->getString('routing.file_offload.header');

        if ($header === '') {
            return null;
        }

        /** @var array<string, string> $prefixMap */
        $prefixMap = $this->config->getArray('routing.file_offload.prefix_map');

        return new FileOffload(header: $header, prefixMap: $prefixMap);
    }
}

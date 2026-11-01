<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\FileResponseException;
use Marko\Routing\Exceptions\RangeNotSatisfiableException;
use Marko\Routing\File\FileSourceInterface;
use Marko\Routing\RoutingConfig;

/**
 * Builds FileResponses with the file offload from routing.file_offload applied.
 *
 * Inject it into controllers that serve files. FileResponse::fromSource()
 * without a FileOffload always streams from PHP.
 */
readonly class FileResponseFactory
{
    public function __construct(
        private RoutingConfig $routingConfig,
    ) {}

    /**
     * @throws ConfigNotFoundException|FileResponseException|RangeNotSatisfiableException
     */
    public function fromSource(
        Request $request,
        FileSourceInterface $fileSource,
    ): FileResponse {
        return FileResponse::fromSource($request, $fileSource, $this->routingConfig->fileOffload());
    }
}

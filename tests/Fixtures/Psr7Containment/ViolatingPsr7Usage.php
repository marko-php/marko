<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\Psr7Containment;

use Psr\Http\Message\ResponseInterface;

readonly class ViolatingPsr7Usage
{
    public function handle(ResponseInterface $response): ResponseInterface
    {
        return $response;
    }
}

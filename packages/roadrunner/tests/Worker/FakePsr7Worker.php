<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\WorkerInterface;

/**
 * Drives {@see WorkerRequestHandler} with a fixed queue of PSR-7 requests,
 * yielding null (the "no further requests" signal) once the queue is
 * exhausted, and recording every response handed to respond().
 */
class FakePsr7Worker implements PSR7WorkerInterface
{
    /** @var list<ResponseInterface> */
    public private(set) array $responses = [];

    /**
     * @param list<ServerRequestInterface> $requests
     */
    public function __construct(
        private array $requests,
    ) {}

    public function waitRequest(): ?ServerRequestInterface
    {
        return array_shift($this->requests);
    }

    public function respond(ResponseInterface $response): void
    {
        $this->responses[] = $response;
    }

    public function getWorker(): WorkerInterface
    {
        throw new RuntimeException('FakePsr7Worker does not support getWorker().');
    }
}

<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Worker;

use Marko\Roadrunner\Http\Psr7RequestBridge;
use Marko\Roadrunner\Http\Psr7ResponseBridge;
use Marko\Routing\Router;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Throwable;

/**
 * Drives the RoadRunner accept loop: waits for the next PSR-7 request,
 * bridges it into the framework, routes it through the already-booted
 * application, and bridges the response back.
 *
 * `Application::boot()` runs exactly once, before this class is
 * constructed — the {@see Router} handed to the constructor is the only
 * way this class reaches the application, and nothing in here can trigger
 * another boot.
 */
readonly class WorkerRequestHandler
{
    private const string PRODUCTION_ERROR_BODY = 'Internal Server Error';

    public function __construct(
        private PSR7WorkerInterface $psr7Worker,
        private Router $router,
        private Psr7RequestBridge $requestBridge,
        private Psr7ResponseBridge $responseBridge,
        private WorkerLogger $logger,
        private bool $development = false,
    ) {}

    public function run(): void
    {
        while (($psr7Request = $this->psr7Worker->waitRequest()) !== null) {
            $this->psr7Worker->respond($this->handleOne($psr7Request));
        }
    }

    private function handleOne(
        ServerRequestInterface $psr7Request,
    ): ResponseInterface {
        $outputBufferLevel = ob_get_level();
        ob_start();

        try {
            $request = $this->requestBridge->bridge($psr7Request);
            $response = $this->router->handle($request);

            return $this->responseBridge->bridge($response);
        } catch (Throwable $throwable) {
            // Intentional catch-all: one bad request must never kill a
            // long-running worker. Log it, answer with a 500, keep serving.
            $this->logger->error($throwable);

            return $this->errorResponse($throwable);
        } finally {
            while (ob_get_level() > $outputBufferLevel) {
                ob_end_clean();
            }
        }
    }

    private function errorResponse(
        Throwable $throwable,
    ): ResponseInterface {
        $body = $this->development
            ? sprintf("%s: %s\n\n%s", $throwable::class, $throwable->getMessage(), $throwable->getTraceAsString())
            : self::PRODUCTION_ERROR_BODY;

        return new Psr7Response(status: 500, body: $body);
    }
}

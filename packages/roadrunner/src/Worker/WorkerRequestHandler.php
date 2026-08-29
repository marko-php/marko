<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Worker;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Contracts\ResettableInterface;
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
        private ContainerInterface $container,
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
            // Reset before, not after: a request that throws below, or a
            // worker killed mid-request, must never leave the *next*
            // request with stale state.
            $this->resetResolvedServices();

            $request = $this->requestBridge->bridge($psr7Request);
            $response = $this->router->handle($request);

            return $this->responseBridge->bridge($response);
        } catch (Throwable $throwable) {
            // Intentional catch-all: one bad request must never kill a
            // long-running worker. Log it, answer with a 500, keep serving.
            // This also covers a reset() failure above — silently
            // swallowing that would be a cross-user data leak, so it must
            // fail this request exactly like any other thrown error.
            $this->logger->error($throwable);

            return $this->errorResponse($throwable);
        } finally {
            while (ob_get_level() > $outputBufferLevel) {
                ob_end_clean();
            }
        }
    }

    /**
     * Clears every already-resolved ResettableInterface instance —
     * discovered generically via ContainerInterface::resolvedInstances(),
     * never by a hardcoded per-package list. Only instances the container
     * has already built are touched, since resolvedInstances() never forces
     * instantiation: a service the current request never used is never
     * constructed just to reset it.
     *
     * Reset order is fixed and deterministic — ascending by container
     * binding identifier — so it never depends on which services happened
     * to be resolved first for a given request, in case a future
     * resettable's reset() needs to run relative to another's.
     */
    private function resetResolvedServices(): void
    {
        /** @var array<string, ResettableInterface> $resettables */
        $resettables = $this->container->resolvedInstances(ResettableInterface::class);
        ksort($resettables);

        foreach ($resettables as $resettable) {
            $resettable->reset();
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

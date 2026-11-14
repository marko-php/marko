<?php

declare(strict_types=1);

namespace Marko\Debugbar\Controller;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Debugbar\Access\AccessGate;
use Marko\Debugbar\Rendering\ProfilerPageRenderer;
use Marko\Debugbar\Storage\DebugbarStorage;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class ProfilerController
{
    private readonly AccessGate $access;

    public function __construct(
        ConfigRepositoryInterface $config,
        private readonly DebugbarStorage $storage,
        private readonly ProfilerPageRenderer $renderer = new ProfilerPageRenderer(),
        ?AccessGate $access = null,
    ) {
        $this->access = $access ?? new AccessGate($config);
    }

    #[Get('/_debugbar')]
    public function index(
        Request $request,
    ): Response {
        if (! $this->allowed()) {
            return $this->notFound();
        }

        return Response::html($this->renderer->index($this->storage->all()));
    }

    #[Get('/_debugbar/{id}')]
    public function show(
        Request $request,
        string $id,
    ): Response {
        if (! $this->allowed()) {
            return $this->notFound();
        }

        $dataset = $this->storage->get($id);

        if ($dataset === null) {
            return $this->notFound();
        }

        return Response::html($this->renderer->show($dataset));
    }

    #[Get('/_debugbar/{id}/json')]
    public function json(
        Request $request,
        string $id,
    ): Response {
        if (! $this->allowed()) {
            return $this->notFound();
        }

        $dataset = $this->storage->get($id);

        if ($dataset === null) {
            return $this->notFound();
        }

        return Response::json($dataset);
    }

    #[Delete('/_debugbar')]
    public function clear(
        Request $request,
    ): Response {
        if (! $this->allowed()) {
            return $this->notFound();
        }

        return Response::json([
            'deleted' => $this->storage->clear(),
        ]);
    }

    /**
     * Profiler routes refuse in production (unless explicitly allowed) and for any
     * client outside the allowlist, including requests relayed by an untrusted proxy.
     */
    private function allowed(): bool
    {
        return $this->access->enabled()
            && $this->access->clientAllowed($_SERVER);
    }

    private function notFound(): Response
    {
        return new Response('Not Found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}

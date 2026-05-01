<?php

declare(strict_types=1);

namespace Marko\Debugbar\Controller;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Debugbar\Rendering\ProfilerPageRenderer;
use Marko\Debugbar\Storage\DebugbarStorage;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class ProfilerController
{
    public function __construct(
        private readonly ConfigRepositoryInterface $config,
        private readonly DebugbarStorage $storage,
        private readonly ProfilerPageRenderer $renderer = new ProfilerPageRenderer(),
    ) {}

    #[Get('/_debugbar')]
    public function index(Request $request): Response
    {
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
    public function clear(Request $request): Response
    {
        if (! $this->allowed()) {
            return $this->notFound();
        }

        return Response::json([
            'deleted' => $this->storage->clear(),
        ]);
    }

    private function allowed(): bool
    {
        if (! $this->configBool('debugbar.enabled', false)) {
            return false;
        }

        if ($this->configBool('debugbar.route.open', false)) {
            return true;
        }

        $allowed = $this->configArray('debugbar.route.allowed_ips', ['127.0.0.1', '::1']);
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (! is_scalar($remoteAddress)) {
            return false;
        }

        return in_array((string) $remoteAddress, array_map('strval', $allowed), true);
    }

    private function notFound(): Response
    {
        return new Response('Not Found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private function configBool(
        string $key,
        bool $default,
    ): bool {
        if (! $this->config->has($key)) {
            return $default;
        }

        $value = $this->config->get($key);

        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $normalized ?? (bool) $value;
        }

        return $default;
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    private function configArray(
        string $key,
        array $default,
    ): array {
        if (! $this->config->has($key)) {
            return $default;
        }

        $value = $this->config->get($key);

        if (! is_array($value)) {
            return $default;
        }

        return array_values(array_map('strval', array_filter($value, 'is_scalar')));
    }
}

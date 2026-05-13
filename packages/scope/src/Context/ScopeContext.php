<?php

declare(strict_types=1);

namespace Marko\Scope\Context;

use Marko\Scope\Exceptions\ScopeContextException;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Registry\ScopeRegistryInterface;

/**
 * Holds the current scope per axis for the active request.
 *
 * Lifecycle: mutable singleton, intended to be set up once per HTTP request / CLI command /
 * queue job. In long-running PHP processes (FPM workers, queue daemons), the bootstrap layer
 * MUST call clearAll() between requests/jobs to avoid cross-request leakage.
 */
class ScopeContext
{
    /** @var array<string, string> */
    private array $state = [];

    public function __construct(
        private readonly ScopeRegistryInterface $registry,
    ) {}

    /**
     * @throws UnknownAxisException|ScopeContextException
     */
    public function in(
        string $axis,
        string $path,
    ): static
    {
        if (!$this->registry->hasAxis($axis)) {
            throw UnknownAxisException::forAxis($axis);
        }

        $hierarchy = $this->registry->getHierarchy($axis);

        if (!$hierarchy->exists($path)) {
            throw ScopeContextException::invalidPath($axis, $path);
        }

        $this->state[$axis] = $path;

        return $this;
    }

    public function get(string $axis): ?string
    {
        return $this->state[$axis] ?? null;
    }

    public function clear(string $axis): void
    {
        unset($this->state[$axis]);
    }

    public function clearAll(): void
    {
        $this->state = [];
    }

    /**
     * @return list<string>
     */
    public function activeAxes(): array
    {
        return array_keys($this->state);
    }

    public function registry(): ScopeRegistryInterface
    {
        return $this->registry;
    }
}

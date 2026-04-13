<?php

declare(strict_types=1);

namespace Marko\Inertia\Props;

use Marko\Routing\Http\Request;

readonly class PropertyContext
{
    /**
     * @param array<string, mixed> $props
     * @param array<string> $only
     * @param array<string> $except
     * @param array<string> $loadedOnce
     */
    public function __construct(
        public Request $request,
        public string $component,
        public string $key,
        public array $props,
        public bool $isPartial,
        public array $only = [],
        public array $except = [],
        public array $loadedOnce = [],
    ) {}

    public function isOnceLoaded(string $key): bool
    {
        return in_array($key, $this->loadedOnce, true);
    }
}

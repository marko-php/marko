<?php

declare(strict_types=1);

namespace Marko\Inertia\Events;

use Marko\Core\Event\Event;
use Marko\Inertia\Props\PropArray;
use Marko\Routing\Http\Request;

class InertiaRenderingEvent extends Event
{
    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $sharedProps
     */
    public function __construct(
        public readonly Request $request,
        public readonly string $component,
        public array $props = [],
        public array $sharedProps = [],
    ) {}

    public function share(
        string|array $key,
        mixed $value = null,
    ): void {
        if (is_array($key)) {
            foreach ($key as $propKey => $propValue) {
                if (! is_string($propKey)) {
                    continue;
                }

                PropArray::set($this->sharedProps, $propKey, $propValue);
            }

            return;
        }

        PropArray::set($this->sharedProps, $key, $value);
    }
}

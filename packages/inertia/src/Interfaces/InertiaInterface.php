<?php

declare(strict_types=1);

namespace Marko\Inertia\Interfaces;

use Marko\Inertia\Props\AlwaysProp;
use Marko\Inertia\Props\DeferProp;
use Marko\Inertia\Props\MergeProp;
use Marko\Inertia\Props\OnceProp;
use Marko\Inertia\Props\OptionalProp;
use Marko\Inertia\Props\ScrollProp;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

interface InertiaInterface
{
    /**
     * Render an Inertia component response.
     *
     * @param string $component Component name (e.g. 'Dashboard/Index')
     * @param array<string, mixed> $props Props passed to the page component
     */
    public function render(
        string $component,
        array $props = [],
        ?Request $request = null,
    ): Response;

    /**
     * Return an Inertia location response or a standard redirect.
     */
    public function location(
        string $url,
        ?Request $request = null,
    ): Response;

    /**
     * Share one or more props with all future responses.
     */
    public function share(
        string|array $key,
        mixed $value = null,
    ): void;

    public function flash(
        string|array $key,
        mixed $value = null,
    ): void;

    /**
     * Flush currently shared props, typically at the beginning of a request.
     */
    public function flushShared(): void;

    /**
     * Get all currently shared props.
     *
     * @return array<string, mixed>
     */
    public function shared(): array;

    public function optional(
        mixed $value,
    ): OptionalProp;

    public function always(
        mixed $value,
    ): AlwaysProp;

    public function defer(
        mixed $value,
        string $group = 'default',
    ): DeferProp;

    public function merge(
        mixed $value,
    ): MergeProp;

    public function deepMerge(
        mixed $value,
    ): MergeProp;

    public function once(
        mixed $value,
        ?string $key = null,
    ): OnceProp;

    public function scroll(
        mixed $value,
        string $wrapper = 'data',
        ProvidesScrollMetadata|callable|array|null $metadata = null,
    ): ScrollProp;
}

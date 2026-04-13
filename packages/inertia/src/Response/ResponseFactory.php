<?php

declare(strict_types=1);

namespace Marko\Inertia\Response;

use Closure;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Inertia\InertiaConfig;
use Marko\Inertia\InertiaFlashStore;
use Marko\Inertia\Interfaces\ComponentResolverInterface;
use Marko\Inertia\Interfaces\ProvidesScrollMetadata;
use Marko\Inertia\Interfaces\RootRendererInterface;
use Marko\Inertia\Props\AlwaysProp;
use Marko\Inertia\Props\DeferProp;
use Marko\Inertia\Props\MergeProp;
use Marko\Inertia\Props\OnceProp;
use Marko\Inertia\Props\OptionalProp;
use Marko\Inertia\Props\PropArray;
use Marko\Inertia\Props\PropsResolver;
use Marko\Inertia\Props\ScrollProp;

class ResponseFactory
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(
        private readonly InertiaConfig $config,
        private readonly ComponentResolverInterface $components,
        private readonly RootRendererInterface $rootRenderer,
        private readonly EventDispatcherInterface $events,
        private readonly PropsResolver $propsResolver,
        private readonly ?Closure $sessionResolver = null,
        private readonly ?Closure $pageMetadataResolver = null,
    ) {}

    /**
     * @param  array<string, mixed>  $props
     */
    public function render(
        string $component,
        array $props = [],
    ): Response {
        if ($this->config->shouldEnsurePagesExist()) {
            $this->components->resolve($component);
        }

        return new Response(
            component: $component,
            props: $props,
            sharedProps: $this->shared,
            config: $this->config,
            rootRenderer: $this->rootRenderer,
            events: $this->events,
            propsResolver: $this->propsResolver,
            flash: new InertiaFlashStore($this->sessionResolver),
            pageMetadataResolver: $this->pageMetadataResolver,
        );
    }

    public function share(
        string|array $key,
        mixed $value = null,
    ): void {
        if (is_array($key)) {
            foreach ($key as $propKey => $propValue) {
                if (! is_string($propKey)) {
                    continue;
                }

                PropArray::set($this->shared, $propKey, $propValue);
            }

            return;
        }

        PropArray::set($this->shared, $key, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function shared(): array
    {
        return $this->shared;
    }

    public function flushShared(): void
    {
        $this->shared = [];
    }

    public function flash(
        string|array $key,
        mixed $value = null,
    ): void {
        (new InertiaFlashStore($this->sessionResolver))->flash($key, $value);
    }

    public function optional(
        mixed $value,
    ): OptionalProp {
        return new OptionalProp($value);
    }

    public function always(
        mixed $value,
    ): AlwaysProp {
        return new AlwaysProp($value);
    }

    public function defer(
        mixed $value,
        string $group = 'default',
    ): DeferProp {
        return new DeferProp($value, $group);
    }

    public function merge(
        mixed $value,
    ): MergeProp {
        return new MergeProp($value);
    }

    public function deepMerge(
        mixed $value,
    ): MergeProp {
        return new MergeProp($value, deepMerge: true);
    }

    public function once(
        mixed $value,
        ?string $key = null,
    ): OnceProp {
        return new OnceProp($value, $key);
    }

    public function scroll(
        mixed $value,
        string $wrapper = 'data',
        ProvidesScrollMetadata|callable|array|null $metadata = null,
    ): ScrollProp {
        return new ScrollProp($value, $wrapper, $metadata);
    }
}

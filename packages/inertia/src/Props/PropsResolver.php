<?php

declare(strict_types=1);

namespace Marko\Inertia\Props;

use Closure;
use Marko\Inertia\Contracts\Deferrable;
use Marko\Inertia\Contracts\Mergeable;
use Marko\Inertia\Contracts\Onceable;
use Marko\Inertia\Enums\InertiaHeaderEnum;
use Marko\Inertia\Interfaces\ProvidesInertiaProperties;
use Marko\Inertia\Interfaces\ProvidesInertiaProperty;
use Marko\Inertia\Rendering\RenderContext;
use Marko\Routing\Http\Request;

class PropsResolver
{
    /**
     * @param  array<int|string, mixed>  $props
     */
    public function resolve(
        Request $request,
        string $component,
        array $props,
    ): ResolvedProps {
        $props = $this->resolvePropertyProviders($request, $component, $props);

        $isInertiaRequest = $this->isInertiaRequest($request);
        $isPartial = $isInertiaRequest
            && $request->header(InertiaHeaderEnum::PARTIAL_COMPONENT->value) === $component;

        $only = $isPartial
            ? $this->parseHeaderList($request->header(InertiaHeaderEnum::PARTIAL_DATA->value))
            : [];
        $except = $isPartial
            ? $this->parseHeaderList($request->header(InertiaHeaderEnum::PARTIAL_EXCEPT->value))
            : [];
        $loadedOnce = $isInertiaRequest
            ? $this->parseHeaderList($request->header(InertiaHeaderEnum::EXCEPT_ONCE->value))
            : [];
        $reset = $isInertiaRequest
            ? $this->parseHeaderList($request->header(InertiaHeaderEnum::RESET->value))
            : [];

        $resolved = [];
        $metadata = [
            'deferredProps' => [],
            'mergeProps' => [],
            'prependProps' => [],
            'deepMergeProps' => [],
            'onceProps' => [],
            'scrollProps' => [],
        ];

        foreach ($props as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $context = new PropertyContext(
                request: $request,
                component: $component,
                key: $key,
                props: $props,
                isPartial: $isPartial,
                only: $only,
                except: $except,
                loadedOnce: $loadedOnce,
            );

            if ($this->excludeFromInitialResponse(
                $request,
                $component,
                $value,
                $key,
                $isPartial,
                $loadedOnce,
                $reset,
                $metadata,
            )) {
                continue;
            }

            if (! $this->shouldInclude($context, $value, $isInertiaRequest)) {
                continue;
            }

            $resolved[$key] = $this->resolveValue($context, $value);
            $this->collectMetadata($request, $component, $value, $key, $isPartial, $only, $except, $reset, $metadata);
        }

        return new ResolvedProps(
            props: $resolved,
            metadata: array_filter($metadata, static fn (array $value): bool => $value !== []),
        );
    }

    private function shouldInclude(
        PropertyContext $context,
        mixed $value,
        bool $isInertiaRequest,
    ): bool {
        if ($value instanceof ProvidesInertiaProperty) {
            return $value->shouldInclude($context);
        }

        if (! $context->isPartial || ! $isInertiaRequest) {
            return true;
        }

        if ($context->only !== []) {
            return in_array($context->key, $context->only, true);
        }

        if ($context->except !== []) {
            return ! in_array($context->key, $context->except, true);
        }

        return true;
    }

    private function resolveValue(
        PropertyContext $context,
        mixed $value,
    ): mixed {
        if ($value instanceof ProvidesInertiaProperty) {
            $value = $value->resolve($context);
        }

        if (is_callable($value) && ! $value instanceof Closure) {
            $value = $value();
        }

        if ($value instanceof Closure) {
            $value = $value();
        }

        if ($value instanceof ProvidesInertiaProperty) {
            $value = $value->resolve($context);
        }

        return $value;
    }

    /**
     * @return array<string>
     */
    private function parseHeaderList(
        ?string $value,
    ): array {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $value),
        )));
    }

    private function isInertiaRequest(
        Request $request,
    ): bool {
        return strtolower((string) $request->header(InertiaHeaderEnum::INERTIA->value, '')) === 'true';
    }

    /**
     * @param  array<int|string, mixed>  $props
     * @return array<string, mixed>
     */
    private function resolvePropertyProviders(
        Request $request,
        string $component,
        array $props,
    ): array {
        $resolved = [];
        $context = new RenderContext($component, $request);

        foreach ($props as $key => $value) {
            if (is_int($key) && $value instanceof ProvidesInertiaProperties) {
                $resolved = array_replace($resolved, $value->toInertiaProperties($context));

                continue;
            }

            if (is_string($key)) {
                $resolved[$key] = $value;
            }
        }

        return $this->unpackDotProps($resolved);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function unpackDotProps(
        array $props,
    ): array {
        $resolved = [];

        foreach ($props as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            PropArray::set($resolved, $key, $value);
        }

        return $resolved;
    }

    /**
     * @param  array<string>  $loadedOnce
     * @param  array<string>  $reset
     * @param  array<string, array<string, mixed>>  $metadata
     */
    private function excludeFromInitialResponse(
        Request $request,
        string $component,
        mixed $value,
        string $key,
        bool $isPartial,
        array $loadedOnce,
        array $reset,
        array &$metadata,
    ): bool {
        if (! $isPartial) {
            if ($value instanceof Deferrable) {
                $metadata['deferredProps'][$value->group()][] = $key;
                $this->collectMetadata($request, $component, $value, $key, false, [], [], $reset, $metadata);

                return true;
            }

            if ($value instanceof OptionalProp) {
                $this->collectMetadata($request, $component, $value, $key, false, [], [], $reset, $metadata);

                return true;
            }
        }

        if (
            $value instanceof Onceable
            && in_array($value->key() ?? $key, $loadedOnce, true)
            && ! $value->shouldRefresh()
        ) {
            $metadata['onceProps'][$value->key() ?? $key] = [
                'prop' => $key,
            ];

            return true;
        }

        return false;
    }

    /**
     * @param  array<string>  $only
     * @param  array<string>  $except
     * @param  array<string>  $reset
     * @param  array<string, array<string, mixed>>  $metadata
     */
    private function collectMetadata(
        Request $request,
        string $component,
        mixed $value,
        string $key,
        bool $isPartial,
        array $only,
        array $except,
        array $reset,
        array &$metadata,
    ): void {
        if (
            $value instanceof Mergeable
            && $value->shouldMerge()
            && ! in_array($key, $reset, true)
            && $this->includedInMetadata($key, $isPartial, $only, $except)
        ) {
            if ($value->shouldDeepMerge()) {
                $metadata['deepMergeProps'][] = $key;
            } elseif ($value->shouldPrepend()) {
                $metadata['prependProps'][] = $key;
            } else {
                $metadata['mergeProps'][] = $key;
            }
        }

        if ($value instanceof Onceable && $this->includedInMetadata($key, $isPartial, $only, $except)) {
            $metadata['onceProps'][$value->key() ?? $key] = [
                'prop' => $key,
            ];
        }

        if ($value instanceof ScrollProp && $this->includedInMetadata($key, $isPartial, $only, $except)) {
            $scrollContext = new RenderContext($component, $request);
            $metadata['scrollProps'][$key] = [
                ...$value->metadata($scrollContext),
                'reset' => in_array($key, $reset, true),
            ];
        }
    }

    /**
     * @param  array<string>  $only
     * @param  array<string>  $except
     */
    private function includedInMetadata(
        string $key,
        bool $isPartial,
        array $only,
        array $except,
    ): bool {
        if (! $isPartial) {
            return true;
        }

        if ($only !== [] && ! in_array($key, $only, true)) {
            return false;
        }

        return ! in_array($key, $except, true);
    }
}

<?php

declare(strict_types=1);

namespace Marko\Inertia\Props;

use Marko\Inertia\Contracts\Mergeable;
use Marko\Inertia\Interfaces\ProvidesInertiaProperty;
use Marko\Inertia\Interfaces\ProvidesScrollMetadata;
use Marko\Inertia\Rendering\RenderContext;

final readonly class ScrollProp implements Mergeable, ProvidesInertiaProperty
{
    /**
     * @param array<string, mixed>|ProvidesScrollMetadata|callable(RenderContext): array<string, mixed>|null $metadata
     */
    public function __construct(
        private mixed $value,
        private string $wrapper = 'data',
        private mixed $metadata = null,
    ) {}

    public function shouldMerge(): bool
    {
        return true;
    }

    public function shouldDeepMerge(): bool
    {
        return false;
    }

    public function shouldPrepend(): bool
    {
        return false;
    }

    public function shouldInclude(PropertyContext $context): bool
    {
        if (! $context->isPartial) {
            return true;
        }

        if ($context->only !== []) {
            return in_array($context->key, $context->only, true);
        }

        return ! in_array($context->key, $context->except, true);
    }

    public function resolve(PropertyContext $context): mixed
    {
        $value = $this->value;

        if (is_callable($value)) {
            $value = $value();
        }

        if ($this->wrapper === '') {
            return $value;
        }

        if (is_array($value) && array_key_exists($this->wrapper, $value)) {
            return $value;
        }

        return [
            $this->wrapper => $value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(RenderContext $context): array
    {
        $metadata = $this->metadata;

        if ($metadata instanceof ProvidesScrollMetadata) {
            return $metadata->toScrollMetadata($context);
        }

        if (is_callable($metadata)) {
            $metadata = $metadata($context);
        }

        return is_array($metadata) ? $metadata : [];
    }
}

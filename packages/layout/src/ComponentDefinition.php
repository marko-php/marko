<?php

declare(strict_types=1);

namespace Marko\Layout;

use ReflectionClass;
use ReflectionException;

readonly class ComponentDefinition
{
    /**
     * @param array<int, string> $handles
     * @param array<int, string> $slots
     */
    public function __construct(
        public string $className,
        public string $template,
        public string|null $slot,
        public array $handles,
        public array $slots,
        public int $sortOrder = 0,
        public string|null $before = null,
        public string|null $after = null,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function hasDataMethod(): bool
    {
        $reflection = new ReflectionClass($this->className);

        return $reflection->hasMethod('data');
    }

    public function hasSubSlots(): bool
    {
        return $this->slots !== [];
    }
}

<?php

declare(strict_types=1);

namespace Marko\Scope\Resolution;

readonly class ScopeWalkResult
{
    private function __construct(
        private bool $found,
        private mixed $val,
    ) {}

    public static function found(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function notFound(): self
    {
        return new self(false, null);
    }

    public function isFound(): bool
    {
        return $this->found;
    }

    public function value(): mixed
    {
        return $this->val;
    }
}

<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<string, array<string>>
 */
class ValidationErrors implements Countable, IteratorAggregate
{
    /**
     * @param array<string, array<string>> $errors
     */
    public function __construct(
        private array $errors = [],
    ) {}

    public function add(
        string $field,
        string $message,
    ): self {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;

        return $this;
    }

    public function has(
        string $field,
    ): bool {
        return isset($this->errors[$field]) && $this->errors[$field] !== [];
    }

    /**
     * @return array<string>
     */
    public function get(
        string $field,
    ): array {
        return $this->errors[$field] ?? [];
    }

    public function first(
        string $field,
    ): ?string {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * @return array<string, array<string>>
     */
    public function all(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->errors);
    }

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function count(): int
    {
        return array_sum(array_map('count', $this->errors));
    }

    /**
     * @return Traversable<string, array<string>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->errors);
    }

    /**
     * @return array<string>
     */
    public function toFlatArray(): array
    {
        $messages = [];

        foreach ($this->errors as $field => $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $messages[] = "$field: $message";
            }
        }

        return $messages;
    }
}

<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

use InvalidArgumentException;

/**
 * Another field a rule refers to (`same:billing.email`, `required_if:items.*.type,video`).
 *
 * The path is absolute; inside a wildcard key, each `*` is replaced by the current row's
 * index, so every cross-field rule resolves its references the same way.
 */
readonly class FieldReference
{
    /**
     * @param string $rule The rule name, for messages (e.g. `same`)
     * @param string $path An absolute dot path, which may contain `*`
     */
    public function __construct(
        private string $rule,
        public string $path,
    ) {}

    /**
     * Return the reference with each `*` replaced, in order, by $indexes.
     *
     * @param string $key The rules key being validated
     * @param list<string> $indexes The array keys the key's wildcards matched, in order
     *
     * @throws InvalidArgumentException When the path has more wildcards than $indexes
     */
    public function forWildcardIndexes(
        string $key,
        array $indexes,
    ): self {
        $path = DataPath::fillWildcards($this->path, $indexes)
            ?? throw new InvalidArgumentException(
                "The $this->rule:$this->path rule has more wildcards than the $key key it validates",
            );

        return new self($this->rule, $path);
    }

    /**
     * Whether the referenced key exists in the data, even holding null.
     *
     * @param array<mixed> $data
     */
    public function has(
        array $data,
    ): bool {
        return DataPath::has($data, $this->path);
    }

    /**
     * The referenced value, or null when the key is absent.
     *
     * @param array<mixed> $data
     */
    public function get(
        array $data,
    ): mixed {
        return DataPath::get($data, $this->path);
    }

    /**
     * Whether the referenced value is present and not empty.
     *
     * @param array<mixed> $data
     */
    public function isFilled(
        array $data,
    ): bool {
        return !EmptyValue::is($this->get($data));
    }
}

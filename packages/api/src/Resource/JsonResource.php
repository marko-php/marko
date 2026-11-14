<?php

declare(strict_types=1);

namespace Marko\Api\Resource;

use JsonException;
use Marko\Api\Contracts\ResourceCollectionInterface;
use Marko\Api\Contracts\ResourceInterface;
use Marko\Api\Value\ConditionalValue;
use Marko\Api\Value\MissingValue;
use Marko\Routing\Http\Response;

abstract class JsonResource implements ResourceInterface
{
    public function __construct(
        public readonly mixed $resource,
    ) {}

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Transform the resource into its filtered output array, with every
     * ConditionalValue resolved and every MissingValue removed (recursively).
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        return $this->filterArray($this->toArray());
    }

    /**
     * Transform the resource into a JSON HTTP response.
     *
     * @throws JsonException
     */
    public function toResponse(): Response
    {
        return Response::json(['data' => $this->resolve()]);
    }

    /**
     * Wrap a value in a ConditionalValue.
     */
    protected function when(
        bool $condition,
        mixed $value,
    ): ConditionalValue {
        return new ConditionalValue($condition, $value);
    }

    /**
     * Return a MissingValue sentinel to omit a field.
     */
    protected function missing(): MissingValue
    {
        return new MissingValue();
    }

    /**
     * Filter the array, resolving ConditionalValues, removing MissingValues,
     * and recursing into nested arrays, resources, and resource collections.
     * Lists stay lists: removed entries are re-indexed so they encode as JSON arrays.
     *
     * @param array<int|string, mixed> $array
     * @return array<int|string, mixed>
     */
    protected function filterArray(
        array $array,
    ): array {
        $result = [];

        foreach ($array as $key => $value) {
            while ($value instanceof ConditionalValue) {
                $value = $value->resolve();
            }

            if ($value instanceof MissingValue) {
                continue;
            }

            if ($value instanceof self) {
                $value = $value->resolve();
            } elseif (
                $value instanceof ResourceInterface
                || $value instanceof ResourceCollectionInterface
            ) {
                $value = $this->filterArray($value->toArray());
            } elseif (is_array($value)) {
                $value = $this->filterArray($value);
            }

            $result[$key] = $value;
        }

        return array_is_list($array) ? array_values($result) : $result;
    }
}

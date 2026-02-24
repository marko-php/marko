<?php

declare(strict_types=1);

namespace Marko\Api\Resource;

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
     * Transform the resource into a JSON HTTP response.
     */
    public function toResponse(): Response
    {
        return Response::json(['data' => $this->filterArray($this->toArray())]);
    }

    /**
     * Wrap a value in a ConditionalValue.
     */
    protected function when(
        bool $condition,
        mixed $value,
    ): ConditionalValue
    {
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
     * Filter the array, resolving ConditionalValues and removing MissingValues.
     *
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    protected function filterArray(
        array $array,
    ): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if ($value instanceof ConditionalValue) {
                $value = $value->resolve();
            }

            if ($value instanceof MissingValue) {
                continue;
            }

            if ($value instanceof ResourceInterface) {
                $value = $value->toArray();
            }

            $result[$key] = $value;
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace Marko\Search\Value;

readonly class SearchFilter
{
    public function __construct(
        public string $field,
        public FilterOperator $operator,
        public mixed $value,
    ) {}
}

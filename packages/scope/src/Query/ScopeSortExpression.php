<?php

declare(strict_types=1);

namespace Marko\Scope\Query;

use Marko\Scope\Exceptions\ScopeConfigurationException;

readonly class ScopeSortExpression
{
    /**
     * @param list<array{axis: string, path: string}> $paths
     * @throws ScopeConfigurationException
     */
    public function __construct(
        public string $property,
        public string $column,
        public array $paths,
        public string $direction,
        public string $jsonColumn = 'scopes',
    ) {
        if (!in_array($this->direction, ['asc', 'desc'], true)) {
            throw new ScopeConfigurationException(
                message: "Invalid sort direction '$this->direction': must be 'asc' or 'desc'",
                context: "Building ScopeSortExpression for property '$this->property'",
                suggestion: "Use 'asc' for ascending or 'desc' for descending sort direction",
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Marko\Scope\Query;

use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Metadata\ScopeMetadataFactory;

readonly class ScopedOrderByFactory
{
    public function __construct(
        private ScopeMetadataFactory $scopeMetadataFactory,
        private ScopeContext $scopeContext,
        private ScopeSortRendererInterface $scopeSortRenderer,
    ) {}

    public function create(
        string $entityClass,
        string $property,
        string $direction = 'asc',
    ): ScopedOrderBy
    {
        return new ScopedOrderBy(
            property: $property,
            scopeMetadataFactory: $this->scopeMetadataFactory,
            scopeContext: $this->scopeContext,
            scopeSortRenderer: $this->scopeSortRenderer,
            entityClass: $entityClass,
            direction: $direction,
        );
    }
}

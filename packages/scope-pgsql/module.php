<?php

declare(strict_types=1);

use Marko\Scope\PgSql\Query\PgSqlScopeSortRenderer;
use Marko\Scope\Query\ScopeSortRendererInterface;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'bindings' => [
        ScopeSortRendererInterface::class => PgSqlScopeSortRenderer::class,
    ],
];

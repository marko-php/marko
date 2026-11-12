<?php

declare(strict_types=1);

use Marko\Docs\Contract\DocsSearchInterface;
use Marko\DocsFts\FtsSearch;

return [
    'bindings' => [],
    'singletons' => [
        DocsSearchInterface::class => FtsSearch::class,
    ],
    'arguments' => [
        FtsSearch::class => [
            'indexPath' => __DIR__ . '/resources/docs.sqlite',
        ],
    ],
];

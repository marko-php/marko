<?php

declare(strict_types=1);

use Marko\DocsMarkdown\MarkdownRepository;

return [
    'bindings' => [],
    'singletons' => [
        MarkdownRepository::class,
    ],
    'arguments' => [
        MarkdownRepository::class => [
            'docsPath' => __DIR__ . '/docs',
        ],
    ],
];

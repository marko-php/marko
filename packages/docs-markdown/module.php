<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\DocsMarkdown\MarkdownRepository;

return [
    'bindings' => [
        MarkdownRepository::class => function (ContainerInterface $container): MarkdownRepository {
            return new MarkdownRepository(
                docsPath: dirname(__FILE__) . '/docs',
            );
        },
    ],
    'singletons' => [
        MarkdownRepository::class,
    ],
];

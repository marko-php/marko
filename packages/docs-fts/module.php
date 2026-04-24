<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\DocsFts\FtsSearch;
use Marko\DocsMarkdown\MarkdownRepository;

return [
    'bindings' => [],
    'singletons' => [
        DocsSearchInterface::class => fn (Container $c) => new FtsSearch(
            repository: $c->get(MarkdownRepository::class),
            indexPath: __DIR__ . '/resources/docs.sqlite',
        ),
    ],
];

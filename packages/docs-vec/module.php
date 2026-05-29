<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\DocsMarkdown\MarkdownRepository;
use Marko\DocsVec\Query\QueryEmbedder;
use Marko\DocsVec\Runtime\VecRuntime;
use Marko\DocsVec\VecSearch;

return [
    'bindings' => [
        DocsSearchInterface::class => VecSearch::class,
    ],
    'singletons' => [
        DocsSearchInterface::class => fn (Container $c) => new VecSearch(
            repository: $c->get(MarkdownRepository::class),
            runtime: new VecRuntime(__DIR__),
            embedder: new QueryEmbedder(new VecRuntime(__DIR__)),
            indexPath: __DIR__ . '/resources/docs.sqlite',
        ),
    ],
];

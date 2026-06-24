<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\DocsMarkdown\MarkdownRepository;
use Marko\DocsVec\Commands\DownloadExtensionCommand;
use Marko\DocsVec\Commands\DownloadModelCommand;
use Marko\DocsVec\Query\QueryEmbedder;
use Marko\DocsVec\Runtime\VecRuntime;
use Marko\DocsVec\VecSearch;

return [
    // Bindings are intentionally empty: DocsSearchInterface is provided by the
    // singleton factory below. Declaring it here too would register the same
    // interface twice in the same module and trip BindingConflictException.
    'bindings' => [],
    'singletons' => [
        // VecRuntime and the download commands take a scalar $packageRoot the
        // container cannot autowire, so they are constructed explicitly here.
        VecRuntime::class => fn () => new VecRuntime(__DIR__),
        DownloadModelCommand::class => fn () => new DownloadModelCommand(__DIR__),
        DownloadExtensionCommand::class => fn () => new DownloadExtensionCommand(__DIR__),
        DocsSearchInterface::class => fn (Container $c) => new VecSearch(
            repository: $c->get(MarkdownRepository::class),
            runtime: new VecRuntime(__DIR__),
            embedder: new QueryEmbedder(new VecRuntime(__DIR__)),
            indexPath: __DIR__ . '/resources/docs.sqlite',
        ),
    ],
];

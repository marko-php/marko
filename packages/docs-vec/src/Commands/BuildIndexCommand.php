<?php

declare(strict_types=1);

namespace Marko\DocsVec\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DocsVec\Indexing\HybridIndexBuilder;

#[Command(name: 'docs-vec:build', description: 'Build hybrid FTS5 + vector search index for Marko docs')]
class BuildIndexCommand implements CommandInterface
{
    public function __construct(
        private HybridIndexBuilder $builder,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $outputPath = dirname(__DIR__, 2) . '/resources/docs.sqlite';
        $this->builder->build($outputPath);
        $output->writeLine('Hybrid index built at: ' . $outputPath);

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Marko\DocsFts\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DocsFts\Indexing\FtsIndexBuilder;

#[Command(name: 'docs-fts:build', description: 'Build the FTS5 search index for Marko docs')]
class BuildIndexCommand implements CommandInterface
{
    public function __construct(
        private FtsIndexBuilder $builder,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $outputPath = dirname(__DIR__, 2) . '/resources/docs.sqlite';
        $this->builder->build($outputPath);
        $output->writeLine('Index built at: ' . $outputPath);

        return 0;
    }
}

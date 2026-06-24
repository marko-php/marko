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
        $withVectors = $this->builder->build($outputPath);

        if ($withVectors) {
            $output->writeLine('Hybrid FTS5 + vector index built at: ' . $outputPath);
        } else {
            $output->writeLine('FTS5-only index built at: ' . $outputPath);
            $output->writeLine(
                'Note: sqlite-vec extension, ONNX model, or transformers-php unavailable — '
                . 'semantic ranking is off. Run `marko docs-vec:download-extension` and '
                . '`marko docs-vec:download-model` (plus `composer require codewithkyrian/transformers`) to enable it.',
            );
        }

        return 0;
    }
}

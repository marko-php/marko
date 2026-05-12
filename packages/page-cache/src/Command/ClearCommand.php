<?php

declare(strict_types=1);

namespace Marko\PageCache\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\PageCache\Contracts\PageCacheInterface;

/** @noinspection PhpUnused */
#[Command(name: 'page-cache:clear', description: 'Clear the full-page cache')]
readonly class ClearCommand implements CommandInterface
{
    public function __construct(
        private PageCacheInterface $pageCache,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        if ($this->pageCache->clear()) {
            $output->writeLine('Page cache cleared successfully.');

            return 0;
        }

        $output->writeLine('Failed to clear the page cache.');

        return 1;
    }
}

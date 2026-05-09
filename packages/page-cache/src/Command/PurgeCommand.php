<?php

declare(strict_types=1);

namespace Marko\PageCache\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\PageCache\Contracts\PageCacheInterface;

/** @noinspection PhpUnused */
#[Command(name: 'page-cache:purge', description: 'Purge a page cache entry by URL or tag')]
readonly class PurgeCommand implements CommandInterface
{
    public function __construct(
        private PageCacheInterface $pageCache,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $target = $input->getArgument(0);

        if ($target === null) {
            $output->writeLine('Error: No target specified. Provide a URL or use --tag <tag>.');

            return 1;
        }

        if ($input->hasOption('tag')) {
            $success = $this->pageCache->purgeTag($target);
            $output->writeLine($success ? "Tag '$target' purged." : "Failed to purge tag '$target'.");
        } else {
            $success = $this->pageCache->purgeUrl($target);
            $output->writeLine($success ? "URL '$target' purged." : "Failed to purge URL '$target'.");
        }

        return $success ? 0 : 1;
    }
}

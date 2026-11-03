<?php

declare(strict_types=1);

namespace Marko\PageCache\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\PageCache\Contracts\PageCacheInterface;

/** @noinspection PhpUnused */
#[Command(
    name: 'page-cache:purge',
    description: 'Purge a page cache entry by URL or tag',
    destructive: true,
    options: ['tag'],
)]
readonly class PurgeCommand implements CommandInterface
{
    public function __construct(
        private PageCacheInterface $pageCache,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        if ($input->hasOption('tag')) {
            $tag = $input->getOption('tag');

            if ($tag === 'true' || $tag === '') {
                $output->writeLine('Error: --tag requires a value. Usage: page-cache:purge --tag <tag>');

                return 1;
            }

            $success = $this->pageCache->purgeTag($tag);
            $output->writeLine($success ? "Tag '$tag' purged." : "Failed to purge tag '$tag'.");

            return $success ? 0 : 1;
        }

        $url = $input->getArgument(0);

        if ($url === null) {
            $output->writeLine('Error: No target specified. Provide a URL or use --tag <tag>.');

            return 1;
        }

        $success = $this->pageCache->purgeUrl($url);
        $output->writeLine($success ? "URL '$url' purged." : "Failed to purge URL '$url'.");

        return $success ? 0 : 1;
    }
}

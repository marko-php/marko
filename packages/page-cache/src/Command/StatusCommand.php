<?php

declare(strict_types=1);

namespace Marko\PageCache\Command;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\PageCache\Config\PageCacheConfig;

/** @noinspection PhpUnused */
#[Command(name: 'page-cache:status', description: 'Show full-page cache status')]
readonly class StatusCommand implements CommandInterface
{
    public function __construct(
        private PageCacheConfig $config,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $output->writeLine('Driver: ' . $this->config->driver());
        $output->writeLine('Path: ' . $this->config->path());

        return 0;
    }
}

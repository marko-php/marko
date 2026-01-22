<?php

declare(strict_types=1);

namespace Marko\Cache\Command;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'cache:clear', description: 'Clear all cached items')]
class ClearCommand implements CommandInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        if ($this->cache->clear()) {
            $output->writeLine('Cache cleared successfully.');

            return 0;
        }

        $output->writeLine('Failed to clear cache.');

        return 1;
    }
}

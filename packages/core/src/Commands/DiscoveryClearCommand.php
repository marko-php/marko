<?php

declare(strict_types=1);

namespace Marko\Core\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Discovery\DiscoveryCache;

/** @noinspection PhpUnused */
#[Command(name: 'discovery:clear', description: 'Remove the discovery cache')]
readonly class DiscoveryClearCommand implements CommandInterface
{
    public function __construct(
        private DiscoveryCache $discoveryCache,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $this->discoveryCache->clear();

        $output->writeLine('Discovery cache cleared successfully.');

        return 0;
    }
}

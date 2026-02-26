<?php

declare(strict_types=1);

namespace Marko\Amphp\Command;

use Marko\Amphp\EventLoopRunner;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

/** @noinspection PhpUnused */
#[Command(name: 'pubsub:listen', description: 'Start the pub/sub listener')]
readonly class PubSubListenCommand implements CommandInterface
{
    public function __construct(
        private EventLoopRunner $runner,
    ) {}

    public function execute(Input $input, Output $output): int
    {
        $output->writeLine('Starting pub/sub listener...');
        $output->writeLine('Press Ctrl+C to stop.');
        $this->runner->run();
        $output->writeLine('Listener stopped.');

        return 0;
    }
}

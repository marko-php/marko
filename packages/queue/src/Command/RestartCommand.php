<?php

declare(strict_types=1);

namespace Marko\Queue\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\RestartSignalInterface;

/** @noinspection PhpUnused */
#[Command(name: 'queue:restart', description: 'Tell every queue worker to exit after its current job')]
class RestartCommand implements CommandInterface
{
    public function __construct(
        private readonly RestartSignalInterface $signal,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $this->signal->request();
        $output->writeLine('Restart signal sent; workers will exit after their current job.');

        return 0;
    }
}

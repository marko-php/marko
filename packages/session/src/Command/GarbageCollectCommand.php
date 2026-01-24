<?php

declare(strict_types=1);

namespace Marko\Session\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;

/** @noinspection PhpUnused */
#[Command(name: 'session:gc', description: 'Run session garbage collection')]
class GarbageCollectCommand implements CommandInterface
{
    public function __construct(
        private readonly SessionHandlerInterface $handler,
        private readonly SessionConfig $config,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $maxLifetime = $this->config->lifetime() * 60;

        $result = $this->handler->gc($maxLifetime);

        if ($result === false) {
            $output->writeLine('Failed to perform garbage collection.');

            return 1;
        }

        $output->writeLine("Garbage collection complete. Removed $result expired sessions.");

        return 0;
    }
}

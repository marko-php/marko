<?php

declare(strict_types=1);

namespace Marko\Auth\Command;

use Marko\Auth\Contracts\RememberTokenStorageInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'auth:clear-tokens', description: 'Clear expired remember me tokens')]
class ClearTokensCommand implements CommandInterface
{
    public function __construct(
        private readonly RememberTokenStorageInterface $storage,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $force = $this->hasForceFlag($input);

        if ($force) {
            $count = $this->storage->clearAllTokens();

            if ($count === 0) {
                $output->writeLine('No tokens to clear.');
            } else {
                $output->writeLine("Cleared all $count token(s).");
            }
        } else {
            $count = $this->storage->clearExpiredTokens();

            if ($count === 0) {
                $output->writeLine('No expired tokens to clear.');
            } else {
                $output->writeLine("Cleared $count expired token(s).");
            }
        }

        return 0;
    }

    private function hasForceFlag(
        Input $input,
    ): bool {
        foreach ($input->getArguments() as $arg) {
            if ($arg === '--force') {
                return true;
            }
        }

        return false;
    }
}

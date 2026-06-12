<?php

declare(strict_types=1);

namespace Marko\Core\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleRepositoryInterface;

/** @noinspection PhpUnused */
#[Command(name: 'discovery:cache', description: 'Compile the discovery cache')]
readonly class DiscoveryCacheCommand implements CommandInterface
{
    public function __construct(
        private DiscoveryCompiler $discoveryCompiler,
        private DiscoveryCache $discoveryCache,
        private ModuleRepositoryInterface $moduleRepository,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $modules = $this->moduleRepository->all();
        $payload = $this->discoveryCompiler->compile($modules);

        try {
            $this->discoveryCache->write($payload);
        } catch (DiscoveryCacheException $e) {
            $output->writeLine($e->getMessage());
            $output->writeLine($e->getSuggestion());

            return 1;
        }

        $output->writeLine('Discovery cache compiled successfully.');
        $output->writeLine('Cache path: ' . $this->discoveryCache->path());
        $output->writeLine('preferences: ' . count($payload['preferences']));
        $output->writeLine('plugins: ' . count($payload['plugins']));
        $output->writeLine('observers: ' . count($payload['observers']));
        $output->writeLine('commands: ' . count($payload['commands']));

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Marko\DevServer\Command;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevServer\Detection\DockerDetector;
use Marko\DevServer\Process\PidFile;

/** @noinspection PhpUnused */
#[Command(name: 'dev:down', description: 'Stop the development environment', aliases: ['down'])]
readonly class DevDownCommand implements CommandInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
        private DockerDetector $dockerDetector,
        private PidFile $pidFile,
    ) {}

    public function execute(Input $input, Output $output): int
    {
        $entries = $this->pidFile->read();
        $dockerHandled = false;

        if ($entries === []) {
            // No PID file — still check if Docker needs stopping
            $downCommand = $this->resolveDockerDownCommand();
            if ($downCommand !== null) {
                $output->writeLine('Stopping development environment...');
                $output->writeLine("  Stopping Docker: $downCommand");
                exec($downCommand);
                $output->writeLine('Development environment stopped.');
                return 0;
            }

            $output->writeLine('No development services running.');
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry->name === 'docker') {
                $downCommand = $this->resolveDockerDownCommand();
                if ($downCommand !== null) {
                    $output->writeLine("  Stopping Docker: $downCommand");
                    exec($downCommand);
                } else {
                    // Fallback: derive from stored command
                    $fallback = preg_replace('/ up( -d)?$/', ' down', $entry->command);
                    $output->writeLine("  Stopping Docker: $fallback");
                    exec($fallback);
                }
                $dockerHandled = true;
            } elseif ($this->pidFile->isRunning($entry->pid)) {
                $output->writeLine("  Stopping $entry->name (PID $entry->pid)...");
                if (function_exists('posix_kill')) {
                    posix_kill($entry->pid, 15);
                }
            } else {
                $output->writeLine("  $entry->name (PID $entry->pid) already stopped.");
            }
        }

        // If PID file had no docker entry but Docker is configured, stop it
        if (!$dockerHandled) {
            $downCommand = $this->resolveDockerDownCommand();
            if ($downCommand !== null) {
                $output->writeLine("  Stopping Docker: $downCommand");
                exec($downCommand);
            }
        }

        $this->pidFile->clear();
        $output->writeLine('Development environment stopped.');

        return 0;
    }

    private function resolveDockerDownCommand(): ?string
    {
        $dockerConfig = $this->config->get('dev.docker');

        if ($dockerConfig === false) {
            return null;
        }

        if (is_string($dockerConfig)) {
            return (string) preg_replace('/ up( -d)?$/', ' down', $dockerConfig);
        }

        return $this->dockerDetector->detect()['downCommand'] ?? null;
    }
}

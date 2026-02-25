<?php

declare(strict_types=1);

namespace Marko\DevServer\Command;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevServer\Detection\DockerDetector;
use Marko\DevServer\Detection\FrontendDetector;
use Marko\DevServer\Exceptions\DevServerException;
use Marko\DevServer\Process\PidFile;
use Marko\DevServer\Process\ProcessEntry;
use Marko\DevServer\Process\ProcessManager;

/** @noinspection PhpUnused */
#[Command(name: 'dev:up', description: 'Start the development environment', aliases: ['up'])]
readonly class DevUpCommand implements CommandInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
        private DockerDetector $dockerDetector,
        private FrontendDetector $frontendDetector,
        private PidFile $pidFile,
        private ProcessManager $processManager,
    ) {}

    /**
     * @throws ConfigNotFoundException|DevServerException
     */
    public function execute(Input $input, Output $output): int
    {
        $port = (int) ($input->getOption('port') ?? $input->getOption('p') ?? $this->config->getInt('dev.port'));
        $detach = $input->hasOption('detach') || $input->hasOption('d') || $this->config->getBool('dev.detach');
        $dockerConfig = $this->config->get('dev.docker');
        $frontendConfig = $this->config->get('dev.frontend');

        $output->writeLine('Starting development environment...');

        $entries = [];

        // Docker
        if ($dockerConfig !== false) {
            $dockerCommand = is_string($dockerConfig)
                ? $dockerConfig
                : $this->dockerDetector->detect()['upCommand'] ?? null;

            if ($dockerCommand !== null) {
                if ($detach) {
                    $dockerCommand .= ' -d';
                }
                $output->writeLine("  Starting Docker: $dockerCommand");
                $pid = $this->processManager->start('docker', $dockerCommand);
                $entries[] = new ProcessEntry(
                    name: 'docker',
                    pid: $pid,
                    command: $dockerCommand,
                    port: 0,
                    startedAt: date('c'),
                );
            }
        }

        // Frontend
        if ($frontendConfig !== false) {
            $frontendCommand = is_string($frontendConfig)
                ? $frontendConfig
                : $this->frontendDetector->detect();

            if ($frontendCommand !== null) {
                $output->writeLine("  Starting frontend: $frontendCommand");
                $pid = $this->processManager->start('frontend', $frontendCommand);
                $entries[] = new ProcessEntry(
                    name: 'frontend',
                    pid: $pid,
                    command: $frontendCommand,
                    port: 0,
                    startedAt: date('c'),
                );
            }
        }

        // Custom processes
        /** @var array<string, string> $processes */
        $processes = $this->config->get('dev.processes');
        foreach ($processes as $name => $processCommand) {
            $output->writeLine("  Starting $name: $processCommand");
            $pid = $this->processManager->start($name, $processCommand);
            $entries[] = new ProcessEntry(
                name: $name,
                pid: $pid,
                command: $processCommand,
                port: 0,
                startedAt: date('c'),
            );
        }

        // PHP server (always)
        $phpCommand = "php -S localhost:$port -t public/";
        $output->writeLine("  Starting PHP server: php -S localhost:$port");
        $pid = $this->processManager->start('php', $phpCommand);
        $entries[] = new ProcessEntry(
            name: 'php',
            pid: $pid,
            command: $phpCommand,
            port: $port,
            startedAt: date('c'),
        );

        if ($detach) {
            $this->pidFile->write($entries);
            $output->writeLine('Development environment started in background.');
            $output->writeLine("Run 'marko dev:status' to check status.");
            $output->writeLine("Run 'marko dev:down' to stop.");
        } else {
            $output->writeLine('Development environment running. Press Ctrl+C to stop.');
            $this->processManager->runForeground();
        }

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Marko\Log\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Log\Config\LogConfig;
use Psr\Clock\ClockInterface;

/** @noinspection PhpUnused */
#[Command(name: 'log:clear', description: 'Clear old log files', destructive: true, options: ['days'])]
readonly class ClearCommand implements CommandInterface
{
    public function __construct(
        private LogConfig $config,
        private ClockInterface $clock,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $days = $this->parseDaysOption($input);
        $logPath = $this->config->path();

        if (!is_dir($logPath)) {
            $output->writeLine("Log directory does not exist: $logPath");

            return 0;
        }

        $cutoff = $this->clock->now()->getTimestamp() - ($days * 24 * 60 * 60);
        $deletedCount = 0;

        $files = glob($logPath . '/*.log');

        if ($files === false) {
            $output->writeLine("Failed to read log directory: $logPath");

            return 1;
        }

        foreach ($files as $file) {
            $mtime = filemtime($file);

            if ($mtime !== false && $mtime < $cutoff) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }

        $output->writeLine("Deleted $deletedCount log file(s) older than $days days.");

        return 0;
    }

    private function parseDaysOption(
        Input $input,
    ): int {
        $days = $input->getOption('days');

        return $days === null ? $this->config->maxFiles() : max(1, (int) $days);
    }
}

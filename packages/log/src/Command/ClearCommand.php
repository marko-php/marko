<?php

declare(strict_types=1);

namespace Marko\Log\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Log\Config\LogConfig;

#[Command(name: 'log:clear', description: 'Clear old log files')]
class ClearCommand implements CommandInterface
{
    public function __construct(
        private readonly LogConfig $config,
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

        $cutoff = time() - ($days * 24 * 60 * 60);
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
        $args = $input->getArguments();

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--days=')) {
                $value = substr($arg, 7);

                return max(1, (int) $value);
            }
        }

        return $this->config->maxFiles();
    }
}

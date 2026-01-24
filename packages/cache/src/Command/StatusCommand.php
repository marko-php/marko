<?php

declare(strict_types=1);

namespace Marko\Cache\Command;

use Marko\Cache\Config\CacheConfig;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

/** @noinspection PhpUnused */
#[Command(name: 'cache:status', description: 'Show cache statistics')]
readonly class StatusCommand implements CommandInterface
{
    public function __construct(
        private CacheConfig $config,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $driver = $this->config->driver();
        $path = $this->config->path();

        $output->writeLine("Cache Driver: $driver");
        $output->writeLine("Cache Path: $path");

        if ($driver === 'file' && is_dir($path)) {
            $stats = $this->getFileStats($path);
            $output->writeLine("Items: {$stats['count']}");
            $output->writeLine("Total Size: {$stats['size']}");
        }

        return 0;
    }

    /**
     * @return array{count: int, size: string}
     */
    private function getFileStats(
        string $path,
    ): array {
        $files = glob($path . '/*.cache');
        $count = $files !== false ? count($files) : 0;
        $totalSize = 0;

        if ($files !== false) {
            foreach ($files as $file) {
                $size = filesize($file);
                $totalSize += $size !== false ? $size : 0;
            }
        }

        return [
            'count' => $count,
            'size' => $this->formatBytes($totalSize),
        ];
    }

    private function formatBytes(
        int $bytes,
    ): string {
        if ($bytes < 1024) {
            return "$bytes B";
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}

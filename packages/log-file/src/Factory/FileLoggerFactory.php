<?php

declare(strict_types=1);

namespace Marko\Log\File\Factory;

use Marko\Log\Config\LogConfig;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\File\Driver\FileLogger;
use Marko\Log\File\Rotation\DailyRotation;

readonly class FileLoggerFactory
{
    public function __construct(
        private LogConfig $config,
        private LogFormatterInterface $formatter,
    ) {}

    public function create(): LoggerInterface
    {
        return new FileLogger(
            path: $this->config->path(),
            channel: $this->config->channel(),
            minimumLevel: $this->config->level(),
            formatter: $this->formatter,
            rotation: new DailyRotation(),
        );
    }
}

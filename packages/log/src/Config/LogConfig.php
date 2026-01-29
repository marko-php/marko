<?php

declare(strict_types=1);

namespace Marko\Log\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Log\Exceptions\InvalidLogLevelException;
use Marko\Log\LogLevel;

readonly class LogConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function driver(): string
    {
        return $this->config->getString('log.driver');
    }

    public function path(): string
    {
        return $this->config->getString('log.path');
    }

    /**
     * @throws InvalidLogLevelException
     */
    public function level(): LogLevel
    {
        $level = $this->config->getString('log.level');

        return LogLevel::tryFrom($level)
            ?? throw InvalidLogLevelException::forLevel($level);
    }

    public function channel(): string
    {
        return $this->config->getString('log.channel');
    }

    public function format(): string
    {
        return $this->config->getString('log.format');
    }

    public function dateFormat(): string
    {
        return $this->config->getString('log.date_format');
    }

    public function maxFiles(): int
    {
        return $this->config->getInt('log.max_files');
    }

    public function maxFileSize(): int
    {
        return $this->config->getInt('log.max_file_size');
    }
}

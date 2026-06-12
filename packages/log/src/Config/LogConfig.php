<?php

declare(strict_types=1);

namespace Marko\Log\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Log\Exceptions\InvalidLogLevelException;
use Marko\Log\LogLevel;

readonly class LogConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function driver(): string
    {
        return $this->config->getString('log.driver');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function path(): string
    {
        return $this->config->getString('log.path');
    }

    /**
     * @throws ConfigNotFoundException|InvalidLogLevelException
     */
    public function level(): LogLevel
    {
        $level = $this->config->getString('log.level');

        return LogLevel::tryFrom($level)
            ?? throw InvalidLogLevelException::forLevel($level);
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function channel(): string
    {
        return $this->config->getString('log.channel');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function format(): string
    {
        return $this->config->getString('log.format');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function dateFormat(): string
    {
        return $this->config->getString('log.date_format');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function maxFiles(): int
    {
        return $this->config->getInt('log.max_files');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function maxFileSize(): int
    {
        return $this->config->getInt('log.max_file_size');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function escapeNewlines(): bool
    {
        return $this->config->getBool('log.escape_newlines');
    }
}

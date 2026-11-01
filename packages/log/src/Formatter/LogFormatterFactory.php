<?php

declare(strict_types=1);

namespace Marko\Log\Formatter;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Log\Config\LogConfig;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Exceptions\InvalidLogConfigException;

/**
 * Builds the formatter named in 'log.formatter' ('line' or 'json').
 */
readonly class LogFormatterFactory
{
    public function __construct(
        private LogConfig $config,
    ) {}

    /**
     * @throws ConfigNotFoundException|InvalidLogConfigException
     */
    public function create(): LogFormatterInterface
    {
        $formatter = $this->config->formatter();

        return match ($formatter) {
            'line' => new LineFormatter(
                format: $this->config->format(),
                dateFormat: $this->config->dateFormat(),
                escapeNewlines: $this->config->escapeNewlines(),
                redactKeys: $this->config->redactKeys(),
            ),
            'json' => new JsonFormatter(
                redactKeys: $this->config->redactKeys(),
            ),
            default => throw InvalidLogConfigException::unknownFormatter($formatter),
        };
    }
}

<?php

declare(strict_types=1);

namespace Marko\Log\Stream\Handler;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Exceptions\LogWriteException;
use Marko\Log\Stream\Exceptions\InvalidStreamTargetException;

/**
 * Opens the stream named in 'log.stream.target' and builds the handler around it.
 */
readonly class StreamLogHandlerFactory
{
    public const array TARGETS = ['php://stderr', 'php://stdout'];

    public function __construct(
        private ConfigRepositoryInterface $config,
        private LogFormatterInterface $formatter,
    ) {}

    /**
     * @throws ConfigNotFoundException|InvalidStreamTargetException|LogWriteException
     */
    public function create(): StreamLogHandler
    {
        $target = $this->config->getString('log.stream.target');

        if (!in_array($target, self::TARGETS, true)) {
            throw InvalidStreamTargetException::unsupported($target, self::TARGETS);
        }

        // RoadRunner sets RR_MODE in every worker; its relay protocol owns the worker's stdout.
        if ($target === 'php://stdout' && $this->underRoadRunner()) {
            throw InvalidStreamTargetException::stdoutUnderRoadRunner();
        }

        $stream = fopen($target, 'w');

        if ($stream === false) {
            throw LogWriteException::forStream($target, 'the stream could not be opened');
        }

        return new StreamLogHandler($stream, $target, $this->formatter);
    }

    private function underRoadRunner(): bool
    {
        $mode = getenv('RR_MODE');

        return is_string($mode) && $mode !== '';
    }
}

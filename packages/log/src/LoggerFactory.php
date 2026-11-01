<?php

declare(strict_types=1);

namespace Marko\Log;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\ContainerInterface;
use Marko\Log\Config\LogConfig;
use Marko\Log\Exceptions\InvalidLogConfigException;
use Marko\Log\Exceptions\InvalidLogLevelException;
use Marko\Log\Exceptions\NoDriverException;
use Psr\Clock\ClockInterface;

/**
 * Builds the Logger from the handlers named in 'log.handlers'. Each handler class is resolved
 * from the container, so a handler package wires its own construction and Preferences apply.
 */
readonly class LoggerFactory
{
    public function __construct(
        private LogConfig $config,
        private ContainerInterface $container,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws ConfigNotFoundException|InvalidLogConfigException|InvalidLogLevelException|NoDriverException
     */
    public function create(): Logger
    {
        $handlers = [];

        foreach ($this->config->handlers() as $class) {
            $handlers[] = $this->container->get($class);
        }

        return new Logger(
            handlers: $handlers,
            channel: $this->config->channel(),
            minimumLevel: $this->config->level(),
            clock: $this->clock,
        );
    }
}

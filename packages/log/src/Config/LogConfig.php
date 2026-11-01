<?php

declare(strict_types=1);

namespace Marko\Log\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\Exceptions\InvalidLogConfigException;
use Marko\Log\Exceptions\InvalidLogLevelException;
use Marko\Log\Exceptions\NoDriverException;
use Marko\Log\LogLevel;

readonly class LogConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * The handlers named in 'log.handlers', in order, each resolved to its class through 'log.handler_map'.
     *
     * @return array<string, class-string<LogHandlerInterface>> Handler name => handler class
     * @throws ConfigNotFoundException|InvalidLogConfigException|NoDriverException
     */
    public function handlers(): array
    {
        $map = $this->handlerMap();
        $installed = array_keys($map);
        $handlers = [];

        foreach ($this->config->getArray('log.handlers') as $name) {
            if (!is_string($name) || $name === '') {
                throw InvalidLogConfigException::invalidHandlerName($name);
            }

            if (!isset($map[$name])) {
                throw InvalidLogConfigException::unknownHandler($name, $installed);
            }

            if (isset($handlers[$name])) {
                throw InvalidLogConfigException::duplicateHandler($name);
            }

            $handlers[$name] = $map[$name];
        }

        if ($handlers === []) {
            throw InvalidLogConfigException::noHandlers($installed);
        }

        return $handlers;
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function formatter(): string
    {
        return $this->config->getString('log.formatter');
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

    /**
     * @return list<string>
     * @throws ConfigNotFoundException|InvalidLogConfigException
     */
    public function redactKeys(): array
    {
        $keys = [];

        foreach ($this->config->getArray('log.redact_keys') as $key) {
            if (!is_string($key) || $key === '') {
                throw InvalidLogConfigException::invalidRedactKey($key);
            }

            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * @throws ConfigNotFoundException|InvalidLogConfigException
     */
    public function fileMode(): int
    {
        return $this->mode('log.file_mode');
    }

    /**
     * @throws ConfigNotFoundException|InvalidLogConfigException
     */
    public function dirMode(): int
    {
        return $this->mode('log.dir_mode');
    }

    /**
     * Each handler package contributes its own 'log.handler_map' entry through its config/log.php, so
     * the map is absent (not misconfigured) until a handler package is installed.
     *
     * @return array<string, class-string<LogHandlerInterface>>
     * @throws ConfigNotFoundException|InvalidLogConfigException|NoDriverException
     */
    private function handlerMap(): array
    {
        if (!$this->config->has('log.handler_map')) {
            throw NoDriverException::noDriverInstalled();
        }

        $map = [];

        foreach ($this->config->getArray('log.handler_map') as $name => $class) {
            if (!is_string($class) || !is_subclass_of($class, LogHandlerInterface::class)) {
                throw InvalidLogConfigException::invalidHandlerClass((string) $name, $class);
            }

            $map[(string) $name] = $class;
        }

        if ($map === []) {
            throw NoDriverException::noDriverInstalled();
        }

        return $map;
    }

    /**
     * @throws ConfigNotFoundException|InvalidLogConfigException
     */
    private function mode(
        string $key,
    ): int {
        $mode = $this->config->getInt($key);

        if ($mode < 0 || $mode > 0777) {
            throw InvalidLogConfigException::invalidMode($key, $mode);
        }

        return $mode;
    }
}

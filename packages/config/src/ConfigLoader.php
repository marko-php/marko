<?php

declare(strict_types=1);

namespace Marko\Config;

use Marko\Config\Exceptions\ConfigLoadException;
use ParseError;

readonly class ConfigLoader
{
    /**
     * @throws ConfigLoadException
     */
    public function load(
        string $filePath,
    ): array {
        if (!file_exists($filePath)) {
            throw new ConfigLoadException(
                filePath: $filePath,
                message: 'Configuration file not found',
            );
        }

        try {
            $config = require $filePath;
        } catch (ParseError $e) {
            throw new ConfigLoadException(
                filePath: $filePath,
                parseError: $e->getMessage(),
                message: 'Configuration file contains invalid PHP syntax',
                previous: $e,
            );
        }

        if (!is_array($config)) {
            throw new ConfigLoadException(
                filePath: $filePath,
                message: 'Configuration file must return an array',
            );
        }

        return $config;
    }

    /**
     * @throws ConfigLoadException
     */
    public function loadIfExists(
        string $filePath,
    ): ?array {
        if (!file_exists($filePath)) {
            return null;
        }

        return $this->load($filePath);
    }
}

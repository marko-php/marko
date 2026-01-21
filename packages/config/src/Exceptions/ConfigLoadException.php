<?php

declare(strict_types=1);

namespace Marko\Config\Exceptions;

use Throwable;

class ConfigLoadException extends ConfigException
{
    public function __construct(
        private readonly string $filePath,
        string $parseError = '',
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $fullMessage = $message !== ''
            ? sprintf('%s [file: %s]', $message, $this->filePath)
            : sprintf('Failed to load configuration file: %s', $this->filePath);

        $context = $parseError !== ''
            ? sprintf('Parse error: %s', $parseError)
            : '';

        $suggestion = 'Verify the file exists and contains valid PHP syntax returning an array.';

        parent::__construct(
            $fullMessage,
            $context,
            $suggestion,
            $code,
            $previous,
        );
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}

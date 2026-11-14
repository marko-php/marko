<?php

declare(strict_types=1);

namespace Marko\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

class ConfigLoadException extends ConfigException
{
    public function __construct(
        private readonly string $filePath,
        string $parseError = '',
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        string $context = '',
        string $suggestion = '',
    ) {
        $fullMessage = $message !== ''
            ? sprintf('%s [file: %s]', $message, $this->filePath)
            : sprintf('Failed to load configuration file: %s', $this->filePath);

        if (
            $context === ''
            && $parseError !== ''
        ) {
            $context = sprintf('Parse error: %s', $parseError);
        }

        if ($suggestion === '') {
            $suggestion = 'Verify the file exists and contains valid PHP syntax returning an array.';
        }

        parent::__construct(
            message: $fullMessage,
            context: $context,
            suggestion: $suggestion,
            code: $code,
            previous: $previous,
        );
    }

    /**
     * Name the config file that was running when a Marko exception was thrown,
     * keeping the original message, context and suggestion.
     */
    public static function fromFileFailure(
        string $filePath,
        MarkoException $previous,
    ): self {
        return new self(
            filePath: $filePath,
            message: $previous->getMessage(),
            code: (int) $previous->getCode(),
            previous: $previous,
            context: $previous->getContext(),
            suggestion: $previous->getSuggestion(),
        );
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}

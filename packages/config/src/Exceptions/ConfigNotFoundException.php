<?php

declare(strict_types=1);

namespace Marko\Config\Exceptions;

use Throwable;

class ConfigNotFoundException extends ConfigException
{
    public function __construct(
        private readonly string $key,
        string $message = '',
        string $context = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $fullMessage = $message !== ''
            ? sprintf('%s [key: %s]', $message, $this->key)
            : sprintf('Configuration key "%s" not found', $this->key);

        parent::__construct(
            $fullMessage,
            $context,
            'Check your config files or provide a default value when accessing the key.',
            $code,
            $previous,
        );
    }

    public function getKey(): string
    {
        return $this->key;
    }
}

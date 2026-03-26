<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class QueueException extends MarkoException
{
    public static function configFileNotFound(
        string $path,
    ): self {
        return new self(
            message: 'Queue configuration file queue.php not found.',
            context: "Expected config file at: $path",
            suggestion: 'Create a config/queue.php file with your queue configuration.',
        );
    }
}

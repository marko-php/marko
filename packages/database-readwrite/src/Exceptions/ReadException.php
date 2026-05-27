<?php

declare(strict_types=1);

namespace Marko\Database\ReadWrite\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when all replicas fail during a read query.
 */
class ReadException extends MarkoException
{
    /**
     * @param string[] $messages
     */
    public static function allReplicasFailed(array $messages): self
    {
        $detail = implode('; ', $messages);

        return new self(
            message: "All replicas failed to execute the query: $detail",
            context: 'While attempting to route a read query to an available replica',
            suggestion: 'Check that at least one replica is reachable and accepting connections',
        );
    }
}

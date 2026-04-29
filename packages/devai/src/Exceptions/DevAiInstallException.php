<?php

declare(strict_types=1);

namespace Marko\DevAi\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DevAiInstallException extends MarkoException
{
    /**
     * @throws static
     */
    public static function alreadyRegistered(string $projectRoot): static
    {
        return new static(
            message: 'Marko marketplace already registered in .claude/settings.json',
            context: 'While installing the claude-code agent at ' . $projectRoot,
            suggestion: 'Pass --force to overwrite the existing registration. This will preserve unrelated user keys but replace marko-prefixed ones.',
        );
    }
}

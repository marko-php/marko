<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Service;

use Marko\AuthenticationPasskey\Exceptions\InvalidPasskeyNameException;

/**
 * The one rule for passkey names, shared by registration and renaming.
 */
class PasskeyName
{
    /**
     * The trimmed name.
     *
     * @throws InvalidPasskeyNameException When it is empty or longer than InvalidPasskeyNameException::MAX_LENGTH
     */
    public static function normalise(
        string $name,
    ): string {
        $trimmed = trim($name);

        if ($trimmed === '' || mb_strlen($trimmed) > InvalidPasskeyNameException::MAX_LENGTH) {
            throw InvalidPasskeyNameException::forName($name);
        }

        return $trimmed;
    }
}

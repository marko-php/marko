<?php

declare(strict_types=1);

namespace Marko\Hashing\Hash;

use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Exceptions\InvalidHasherConfigException;

readonly class BcryptHasher implements HasherInterface
{
    public const int DEFAULT_COST = 12;

    private int $cost;

    public function __construct(
        ?int $cost = null,
    ) {
        $this->cost = $cost ?? self::DEFAULT_COST;
        $this->validateCost();
    }

    public function hash(
        string $value,
    ): string {
        $hashed = password_hash($value, PASSWORD_BCRYPT, ['cost' => $this->cost]);

        if ($hashed === false) {
            throw new InvalidHasherConfigException(
                message: 'Failed to hash value with bcrypt',
                context: "Cost: $this->cost, PHP version: " . phpversion(),
                suggestion: 'Ensure PHP version is >= 5.3 and cost is between 4 and 31',
            );
        }

        return $hashed;
    }

    public function verify(
        string $value,
        string $hash,
    ): bool {
        return password_verify($value, $hash);
    }

    public function needsRehash(
        string $hash,
    ): bool {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function algorithm(): string
    {
        return 'bcrypt';
    }

    /**
     * @throws InvalidHasherConfigException
     */
    private function validateCost(): void
    {
        if ($this->cost < 4 || $this->cost > 31) {
            throw new InvalidHasherConfigException(
                message: 'Invalid bcrypt cost parameter',
                context: "Cost must be between 4 and 31, got $this->cost",
                suggestion: 'Update config/hashing.php bcrypt cost to a value between 4 and 31',
            );
        }
    }
}

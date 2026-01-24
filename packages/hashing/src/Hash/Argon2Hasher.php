<?php

declare(strict_types=1);

namespace Marko\Hashing\Hash;

use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Exceptions\InvalidHasherConfigException;

readonly class Argon2Hasher implements HasherInterface
{
    public const int DEFAULT_MEMORY = 65536;
    public const int DEFAULT_TIME = 4;
    public const int DEFAULT_THREADS = 1;

    private int $memory;

    private int $time;

    private int $threads;

    /**
     * @throws InvalidHasherConfigException
     */
    public function __construct(
        ?int $memory = null,
        ?int $time = null,
        ?int $threads = null,
    ) {
        $this->memory = $memory ?? self::DEFAULT_MEMORY;
        $this->time = $time ?? self::DEFAULT_TIME;
        $this->threads = $threads ?? self::DEFAULT_THREADS;
        $this->validate();
    }

    public function hash(
        string $value,
    ): string {
        return password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memory,
            'time_cost' => $this->time,
            'threads' => $this->threads,
        ]);
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
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memory,
            'time_cost' => $this->time,
            'threads' => $this->threads,
        ]);
    }

    public function algorithm(): string
    {
        return 'argon2id';
    }

    /**
     * @throws InvalidHasherConfigException
     */
    private function validate(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new InvalidHasherConfigException(
                message: 'Argon2 is not available in this PHP installation',
                context: 'PASSWORD_ARGON2ID constant not defined',
                suggestion: 'Upgrade to PHP >= 7.2 or install Argon2 support',
            );
        }

        if ($this->memory < 8) {
            throw new InvalidHasherConfigException(
                message: 'Invalid Argon2 memory parameter',
                context: "Memory must be >= 8, got $this->memory",
                suggestion: 'Update config/hashing.php argon2id memory to >= 8',
            );
        }

        if ($this->time < 1) {
            throw new InvalidHasherConfigException(
                message: 'Invalid Argon2 time parameter',
                context: "Time must be >= 1, got $this->time",
                suggestion: 'Update config/hashing.php argon2id time to >= 1',
            );
        }

        if ($this->threads < 1) {
            throw new InvalidHasherConfigException(
                message: 'Invalid Argon2 threads parameter',
                context: "Threads must be >= 1, got $this->threads",
                suggestion: 'Update config/hashing.php argon2id threads to >= 1',
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Marko\Encryption\Contracts;

use Marko\Encryption\Exceptions\DecryptionException;
use Marko\Encryption\Exceptions\EncryptionException;

interface EncryptorInterface
{
    /**
     * @throws EncryptionException
     */
    public function encrypt(
        string $value,
    ): string;

    /**
     * @throws DecryptionException
     */
    public function decrypt(
        string $encrypted,
    ): string;
}

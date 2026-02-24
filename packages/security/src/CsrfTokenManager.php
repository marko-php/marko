<?php

declare(strict_types=1);

namespace Marko\Security;

use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Session\Contracts\SessionInterface;

class CsrfTokenManager implements CsrfTokenManagerInterface
{
    private const string SESSION_KEY = '_csrf_token';

    public function __construct(
        private readonly SessionInterface $session,
        private readonly EncryptorInterface $encryptor,
    ) {}

    public function get(): string
    {
        $existing = $this->session->get(self::SESSION_KEY);

        if ($existing !== null) {
            return (string) $existing;
        }

        return $this->generateToken();
    }

    public function validate(
        string $token,
    ): bool {
        $stored = $this->session->get(self::SESSION_KEY);

        if ($stored === null) {
            return false;
        }

        return hash_equals((string) $stored, $token);
    }

    public function regenerate(): string
    {
        return $this->generateToken();
    }

    private function generateToken(): string
    {
        $randomBytes = random_bytes(32);
        $token = $this->encryptor->encrypt($randomBytes);

        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }
}

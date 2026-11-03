<?php

declare(strict_types=1);

namespace Marko\Security;

use Marko\Core\Contracts\ResettableInterface;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Session\Contracts\SessionInterface;
use Override;

class CsrfTokenManager implements CsrfTokenManagerInterface, ResettableInterface
{
    /**
     * The token the session held before this request first wrote one, kept
     * once a write replaced it.
     */
    private ?string $arrivedWith = null;

    private bool $hasWritten = false;

    /**
     * @param string $sessionKey Session key holding the token (security.csrf.session_key)
     */
    public function __construct(
        private readonly SessionInterface $session,
        private readonly EncryptorInterface $encryptor,
        private readonly string $sessionKey = '_csrf_token',
    ) {}

    public function get(): string
    {
        $existing = $this->session->get($this->sessionKey);

        if ($existing !== null) {
            return (string) $existing;
        }

        return $this->generateToken();
    }

    /**
     * Also accepts the token the request arrived with after a rotation in the
     * same request: CsrfMiddleware checks after the route's middleware, and a
     * remember-me login there rotates the token before the check.
     */
    public function validate(
        string $token,
    ): bool {
        $stored = $this->session->get($this->sessionKey);

        if ($stored !== null && hash_equals((string) $stored, $token)) {
            return true;
        }

        return $this->arrivedWith !== null && hash_equals($this->arrivedWith, $token);
    }

    public function regenerate(): string
    {
        return $this->generateToken();
    }

    #[Override]
    public function reset(): void
    {
        $this->arrivedWith = null;
        $this->hasWritten = false;
    }

    private function generateToken(): string
    {
        if (!$this->hasWritten) {
            $existing = $this->session->get($this->sessionKey);
            $this->arrivedWith = $existing !== null ? (string) $existing : null;
            $this->hasWritten = true;
        }

        $randomBytes = random_bytes(32);
        $token = $this->encryptor->encrypt($randomBytes);

        $this->session->set($this->sessionKey, $token);

        return $token;
    }
}

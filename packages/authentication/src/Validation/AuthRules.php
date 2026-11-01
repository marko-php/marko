<?php

declare(strict_types=1);

namespace Marko\Authentication\Validation;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordVerifierInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Config\Exceptions\ConfigNotFoundException;

/**
 * Builds the validation rules that need authentication services. Inject it where
 * rules are declared and pass the rules it returns alongside string rules.
 */
readonly class AuthRules
{
    public function __construct(
        private AuthManager $authManager,
        private PasswordVerifierInterface $passwordVerifier,
        private AuthConfig $config,
    ) {}

    /**
     * A rule that passes when the value is the password of the named guard's
     * authenticated user, or the default guard's when $guard is null.
     */
    public function currentPassword(
        ?string $guard = null,
    ): CurrentPassword {
        return new CurrentPassword($this->authManager->guard($guard), $this->passwordVerifier);
    }

    /**
     * A password strength rule with the app-wide defaults from authentication.password.
     * Call its builder methods, e.g. password()->min(16), to tighten them for one use.
     *
     * @throws AuthException|ConfigNotFoundException
     */
    public function password(): Password
    {
        return new Password(
            min: $this->config->passwordMinLength(),
            mixedCase: $this->config->passwordRequiresMixedCase(),
            letters: $this->config->passwordRequiresLetters(),
            numbers: $this->config->passwordRequiresNumbers(),
            symbols: $this->config->passwordRequiresSymbols(),
        );
    }
}

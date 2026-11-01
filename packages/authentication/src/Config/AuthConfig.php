<?php

declare(strict_types=1);

namespace Marko\Authentication\Config;

use Marko\Authentication\Exceptions\AuthException;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class AuthConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function defaultGuard(): string
    {
        return $this->config->getString('authentication.default.guard');
    }

    public function defaultProvider(): string
    {
        return $this->config->getString('authentication.default.provider');
    }

    /**
     * The default provider name, or null when authentication.default.provider is not set.
     */
    public function defaultProviderOrNull(): ?string
    {
        return $this->config->has('authentication.default.provider')
            ? $this->config->getString('authentication.default.provider')
            : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function guards(): array
    {
        return $this->config->getArray('authentication.guards');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function providers(): array
    {
        return $this->config->getArray('authentication.providers');
    }

    /**
     * The configured providers, or null when authentication.providers is not set.
     *
     * @return array<string, mixed>|null
     */
    public function providersOrNull(): ?array
    {
        return $this->config->has('authentication.providers')
            ? $this->config->getArray('authentication.providers')
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function passwordConfig(): array
    {
        return $this->config->getArray('authentication.password');
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberConfig(): array
    {
        return $this->config->getArray('authentication.remember');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function bcryptCost(): int
    {
        return $this->config->getInt('authentication.password.bcrypt.cost');
    }

    /**
     * The fewest characters AuthRules::password() accepts by default.
     *
     * @throws AuthException|ConfigNotFoundException
     */
    public function passwordMinLength(): int
    {
        return $this->positiveInt('authentication.password.min');
    }

    /**
     * Whether AuthRules::password() requires an uppercase and a lowercase letter by default.
     *
     * @throws ConfigNotFoundException
     */
    public function passwordRequiresMixedCase(): bool
    {
        return $this->config->getBool('authentication.password.mixed_case');
    }

    /**
     * Whether AuthRules::password() requires a letter by default.
     *
     * @throws ConfigNotFoundException
     */
    public function passwordRequiresLetters(): bool
    {
        return $this->config->getBool('authentication.password.letters');
    }

    /**
     * Whether AuthRules::password() requires a number by default.
     *
     * @throws ConfigNotFoundException
     */
    public function passwordRequiresNumbers(): bool
    {
        return $this->config->getBool('authentication.password.numbers');
    }

    /**
     * Whether AuthRules::password() requires a symbol by default.
     *
     * @throws ConfigNotFoundException
     */
    public function passwordRequiresSymbols(): bool
    {
        return $this->config->getBool('authentication.password.symbols');
    }

    /**
     * Remember-me lifetime in minutes.
     *
     * @throws ConfigNotFoundException
     */
    public function rememberLifetime(): int
    {
        return $this->config->getInt('authentication.remember.lifetime');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function rememberCookiePrefix(): string
    {
        return $this->config->getString('authentication.remember.cookie.prefix');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function rememberCookiePath(): string
    {
        return $this->config->getString('authentication.remember.cookie.path');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function rememberCookieDomain(): ?string
    {
        $domain = $this->config->getString('authentication.remember.cookie.domain');

        return $domain !== '' ? $domain : null;
    }

    /**
     * Whether the remember cookie is marked Secure. A null config value follows
     * the session cookie's secure flag so both cookies stay consistent.
     *
     * @throws ConfigNotFoundException
     */
    public function rememberCookieSecure(): bool
    {
        $secure = $this->config->get('authentication.remember.cookie.secure');

        if ($secure === null) {
            return $this->config->getBool('session.cookie.secure');
        }

        return (bool) $secure;
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function rememberCookieHttpOnly(): bool
    {
        return $this->config->getBool('authentication.remember.cookie.http_only');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function rememberCookieSameSite(): string
    {
        return $this->config->getString('authentication.remember.cookie.same_site');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function throttleEnabled(): bool
    {
        return $this->config->getBool('authentication.throttle.enabled');
    }

    /**
     * Failed logins allowed within the decay window before a lockout.
     *
     * @throws ConfigNotFoundException
     */
    public function throttleMaxAttempts(): int
    {
        return $this->config->getInt('authentication.throttle.max_attempts');
    }

    /**
     * Seconds over which failed logins are counted.
     *
     * @throws ConfigNotFoundException
     */
    public function throttleDecaySeconds(): int
    {
        return $this->config->getInt('authentication.throttle.decay_seconds');
    }

    /**
     * Length in seconds of the first lockout; each further lockout doubles it.
     *
     * @throws ConfigNotFoundException
     */
    public function throttleLockoutSeconds(): int
    {
        return $this->config->getInt('authentication.throttle.lockout_seconds');
    }

    /**
     * The longest a lockout can grow to, in seconds.
     *
     * @throws ConfigNotFoundException
     */
    public function throttleMaxLockoutSeconds(): int
    {
        return $this->config->getInt('authentication.throttle.max_lockout_seconds');
    }

    /**
     * The user provider password reset looks users up in.
     *
     * @throws ConfigNotFoundException
     */
    public function passwordResetProvider(): string
    {
        return $this->config->getString('authentication.password_reset.provider');
    }

    /**
     * How long a password reset token stays valid, in seconds.
     *
     * @throws AuthException|ConfigNotFoundException
     */
    public function passwordResetExpireSeconds(): int
    {
        return $this->positiveInt('authentication.password_reset.expire_seconds');
    }

    /**
     * The minimum time between two reset link requests for one user, in seconds.
     *
     * @throws AuthException|ConfigNotFoundException
     */
    public function passwordResetThrottleSeconds(): int
    {
        return $this->positiveInt('authentication.password_reset.throttle_seconds');
    }

    /**
     * How long a password confirmation stays valid, in seconds.
     *
     * @throws AuthException|ConfigNotFoundException
     */
    public function passwordConfirmationTimeoutSeconds(): int
    {
        return $this->positiveInt('authentication.password_confirmation.timeout_seconds');
    }

    /**
     * The URL path RequirePasswordConfirmationMiddleware redirects browsers to.
     *
     * @throws ConfigNotFoundException
     */
    public function passwordConfirmationRoute(): string
    {
        return $this->config->getString('authentication.password_confirmation.route');
    }

    /**
     * Password confirmation attempts one user may make within the decay window.
     *
     * @throws AuthException|ConfigNotFoundException
     */
    public function passwordConfirmationThrottleMaxAttempts(): int
    {
        return $this->positiveInt('authentication.password_confirmation.throttle.max_attempts');
    }

    /**
     * Seconds over which password confirmation attempts are counted.
     *
     * @throws AuthException|ConfigNotFoundException
     */
    public function passwordConfirmationThrottleDecaySeconds(): int
    {
        return $this->positiveInt('authentication.password_confirmation.throttle.decay_seconds');
    }

    /**
     * @throws AuthException|ConfigNotFoundException
     */
    private function positiveInt(
        string $key,
    ): int {
        $value = $this->config->getInt($key);

        if ($value < 1) {
            throw AuthException::invalidPositiveSetting($key, $value);
        }

        return $value;
    }
}

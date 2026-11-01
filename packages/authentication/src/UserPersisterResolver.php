<?php

declare(strict_types=1);

namespace Marko\Authentication;

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\Exceptions\UserPersisterException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\ContainerInterface;

/**
 * Picks the persister that saves a user provider's users after an authentication
 * feature (password reset, email verification, two-factor) changed them.
 *
 * The provider's entry in `authentication.providers` can name a
 * UserPersisterInterface implementation with its `persister` key, mirroring the
 * provider's `class` key. A provider without one, or an app with no
 * `authentication.providers` at all, uses the container's UserPersisterInterface
 * binding. When neither exists, resolution fails loudly instead of silently
 * dropping the change.
 *
 * Persisters are resolved on first use and shared per provider name.
 */
class UserPersisterResolver
{
    /** @var array<string, UserPersisterInterface> */
    private array $persisters = [];

    public function __construct(
        private readonly AuthConfig $config,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @throws UserPersisterException|ConfigNotFoundException
     */
    public function forProvider(
        string $providerName,
    ): UserPersisterInterface {
        return $this->persisters[$providerName] ??= $this->resolve($providerName);
    }

    /**
     * Check that a user from $providerName implements the feature interface a feature
     * changes it through, e.g. CanResetPasswordInterface.
     *
     * @template T of AuthenticatableInterface
     * @param class-string<T> $interface
     * @phpstan-assert T $user
     * @throws UserPersisterException
     */
    public function assertUserSupports(
        AuthenticatableInterface $user,
        string $interface,
        string $providerName,
    ): void {
        if (!$user instanceof $interface) {
            throw UserPersisterException::userMissingInterface($providerName, $user::class, $interface);
        }
    }

    /**
     * @throws UserPersisterException|ConfigNotFoundException
     */
    private function resolve(
        string $providerName,
    ): UserPersisterInterface {
        $providersConfig = $this->config->providersOrNull();

        if ($providersConfig !== null && !array_key_exists($providerName, $providersConfig)) {
            throw UserPersisterException::undefinedPersisterProvider(
                $providerName,
                array_map(strval(...), array_keys($providersConfig)),
            );
        }

        $providerConfig = $providersConfig[$providerName] ?? null;
        $class = is_array($providerConfig) ? ($providerConfig['persister'] ?? null) : null;

        if ($class === null) {
            if (!$this->container->has(UserPersisterInterface::class)) {
                throw UserPersisterException::noPersister($providerName);
            }

            /** @var UserPersisterInterface */
            return $this->container->get(UserPersisterInterface::class);
        }

        if (!is_string($class) || !is_a($class, UserPersisterInterface::class, true)) {
            throw UserPersisterException::invalidPersisterClass(
                $providerName,
                is_string($class) ? $class : get_debug_type($class),
            );
        }

        /** @var UserPersisterInterface */
        return $this->container->get($class);
    }
}

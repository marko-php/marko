<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\OAuth\Config\OAuthConfig;

function oauthConfig(array $values): OAuthConfig
{
    $repository = new readonly class ($values) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $values,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            $segments = explode('.', $key);
            $value = $this->values;

            foreach ($segments as $segment) {
                $value = $value[$segment];
            }

            return $value;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return true;
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $scope);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $scope);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $scope);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $scope);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return $this->get($key, $scope);
        }

        public function all(?string $scope = null): array
        {
            return $this->values;
        }

        public function withScope(string $scope): ConfigRepositoryInterface
        {
            return $this;
        }
    };

    return new OAuthConfig($repository);
}

it('reads oauth configuration values', function (): void {
    $config = oauthConfig([
        'oauth' => require dirname(__DIR__, 2) . '/config/oauth.php',
    ]);

    expect($config->routesEnabled())->toBeTrue()
        ->and($config->routePrefix())->toBe('/oauth')
        ->and($config->managementRoutesEnabled())->toBeFalse()
        ->and($config->privateKeyPath())->toBe('storage/oauth/private.key')
        ->and($config->publicKeyPath())->toBe('storage/oauth/public.key')
        ->and($config->keyPassphrase())->toBeNull()
        ->and($config->accessTokenTtl())->toBe('PT1H')
        ->and($config->refreshTokenTtl())->toBe('P30D')
        ->and($config->authCodeTtl())->toBe('PT10M')
        ->and($config->checkRevocation())->toBeTrue()
        ->and($config->rotateRefreshTokens())->toBeTrue()
        ->and($config->detectRefreshTokenReuse())->toBeTrue()
        ->and($config->rememberConsent())->toBeTrue()
        ->and($config->consentTtl())->toBe('P1Y')
        ->and($config->scopes())->toBe([])
        ->and($config->defaultScopes())->toBe([]);
});

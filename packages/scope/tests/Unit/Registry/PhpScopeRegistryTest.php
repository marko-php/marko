<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Registry\PhpScopeRegistry;
use Marko\Scope\Registry\ScopeRegistryInterface;

function makeConfigStub(array $axes): ConfigRepositoryInterface
{
    return new readonly class ($axes) implements ConfigRepositoryInterface
    {
        public function __construct(private readonly array $axes) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed
        {
            return $this->axes;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool
        {
            return true;
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string
        {
            return '';
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int
        {
            return 0;
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool
        {
            return false;
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float
        {
            return 0.0;
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array
        {
            return $this->axes;
        }

        public function all(?string $scope = null): array
        {
            return $this->axes;
        }

        public function withScope(string $scope): ConfigRepositoryInterface
        {
            return $this;
        }
    };
}

it('loads axes from injected config into PhpScopeRegistry', function (): void {
    $config = makeConfigStub([
        'geo' => ['hierarchy' => ['eu', 'eu.de', 'us']],
        'locale' => ['hierarchy' => ['en', 'fr']],
    ]);

    $registry = new PhpScopeRegistry($config);

    expect($registry)->toBeInstanceOf(ScopeRegistryInterface::class);
});

it('returns ScopeAxis instances from getAxis for registered names', function (): void {
    $config = makeConfigStub([
        'geo' => ['hierarchy' => ['eu', 'eu.de', 'us']],
    ]);

    $registry = new PhpScopeRegistry($config);

    expect($registry->getAxis('geo'))->toBeInstanceOf(ScopeAxis::class)
        ->and($registry->getAxis('geo')->name)->toBe('geo');
});

it('throws UnknownAxisException when getAxis is called with unknown axis', function (): void {
    $config = makeConfigStub([
        'geo' => ['hierarchy' => ['eu', 'eu.de']],
    ]);

    $registry = new PhpScopeRegistry($config);

    expect(fn () => $registry->getAxis('unknown'))->toThrow(UnknownAxisException::class);
});

it('throws ScopeConfigurationException when config has duplicate axis names', function (): void {
    // PHP arrays cannot have duplicate keys, so we test duplicate paths in a hierarchy
    // which is the only way to get duplication at axis registration level.
    // We test duplicate hierarchy paths causing ScopeConfigurationException.
    $config = makeConfigStub([
        'geo' => ['hierarchy' => ['eu', 'eu', 'us']],
    ]);

    expect(fn () => new PhpScopeRegistry($config))->toThrow(ScopeConfigurationException::class);
});

it('throws ScopeConfigurationException when config shape is malformed', function (): void {
    $config = makeConfigStub([
        'geo' => 'not-an-array',
    ]);

    expect(fn () => new PhpScopeRegistry($config))->toThrow(ScopeConfigurationException::class);
});

it('returns the list of all registered axis names in registration order', function (): void {
    $config = makeConfigStub([
        'geo' => ['hierarchy' => ['eu', 'us']],
        'locale' => ['hierarchy' => ['en', 'fr']],
        'channel' => ['hierarchy' => ['web', 'mobile']],
    ]);

    $registry = new PhpScopeRegistry($config);

    expect($registry->listAxes())->toBe(['geo', 'locale', 'channel']);
});

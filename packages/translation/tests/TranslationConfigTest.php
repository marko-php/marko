<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Translation\Config\TranslationConfig;

it('creates TranslationConfig with locale and fallback locale from config', function () {
    $configRepo = new class () implements ConfigRepositoryInterface
    {
        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'translation.locale' => 'fr',
                'translation.fallback_locale' => 'en',
                default => throw new RuntimeException("Unknown key: $key"),
            };
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return in_array($key, ['translation.locale', 'translation.fallback_locale'], true);
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
            return (array) $this->get($key, $scope);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return [];
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };

    $config = new TranslationConfig($configRepo);

    expect($config)->toBeInstanceOf(TranslationConfig::class);
});

it('provides locale and fallbackLocale as readonly properties', function () {
    $configRepo = new class () implements ConfigRepositoryInterface
    {
        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'translation.locale' => 'fr',
                'translation.fallback_locale' => 'en',
                default => throw new RuntimeException("Unknown key: $key"),
            };
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
            return (array) $this->get($key, $scope);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return [];
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };

    $config = new TranslationConfig($configRepo);

    expect($config->locale)->toBe('fr')
        ->and($config->fallbackLocale)->toBe('en');

    $reflection = new ReflectionClass($config);
    $localeProperty = $reflection->getProperty('locale');
    $fallbackProperty = $reflection->getProperty('fallbackLocale');

    expect($localeProperty->isReadOnly())->toBeTrue()
        ->and($fallbackProperty->isReadOnly())->toBeTrue();
});

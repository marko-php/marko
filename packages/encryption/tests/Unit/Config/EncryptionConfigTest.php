<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Encryption\Config\EncryptionConfig;

function createEncryptionConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    return new readonly class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $data,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            if (!$this->has($key, $scope)) {
                throw new ConfigNotFoundException($key);
            }

            return $this->data[$key];
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
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
            return $this->data;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

describe('EncryptionConfig', function (): void {
    it('reads key from config without fallback', function (): void {
        $config = new EncryptionConfig(createEncryptionConfigRepository([
            'encryption.key' => 'test-key-value',
        ]));

        expect($config->key())->toBe('test-key-value');
    });

    it('reads cipher from config without fallback', function (): void {
        $config = new EncryptionConfig(createEncryptionConfigRepository([
            'encryption.cipher' => 'aes-256-cbc',
        ]));

        expect($config->cipher())->toBe('aes-256-cbc');
    });

    it('throws ConfigNotFoundException when key is missing', function (): void {
        $config = new EncryptionConfig(createEncryptionConfigRepository([]));

        $config->key();
    })->throws(ConfigNotFoundException::class);

    it('throws ConfigNotFoundException when cipher is missing', function (): void {
        $config = new EncryptionConfig(createEncryptionConfigRepository([]));

        $config->cipher();
    })->throws(ConfigNotFoundException::class);

    it('config file contains all required keys with defaults', function (): void {
        $configFile = require dirname(__DIR__, 3) . '/config/encryption.php';

        expect($configFile)->toHaveKey('key')
            ->and($configFile)->toHaveKey('cipher');
    });
});

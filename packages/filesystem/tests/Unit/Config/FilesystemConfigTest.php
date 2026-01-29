<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Filesystem\Config\FilesystemConfig;

function createFilesystemMockConfigRepository(
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

describe('FilesystemConfig', function (): void {
    it('reads default from config without fallback', function (): void {
        $config = new FilesystemConfig(createFilesystemMockConfigRepository([
            'filesystem.default' => 'local',
        ]));

        expect($config->defaultDisk())->toBe('local');
    });

    it('reads disk configuration from config without fallback', function (): void {
        $diskConfig = [
            'local' => [
                'driver' => 'local',
                'path' => 'storage',
            ],
        ];

        $config = new FilesystemConfig(createFilesystemMockConfigRepository([
            'filesystem.disks' => $diskConfig,
        ]));

        expect($config->getDisk('local'))->toBe($diskConfig['local']);
    });

    it('config file contains all required keys with defaults', function (): void {
        $configFile = require dirname(__DIR__, 3) . '/config/filesystem.php';

        expect($configFile)->toBeArray()
            ->and($configFile)->toHaveKey('default')
            ->and($configFile)->toHaveKey('disks')
            ->and($configFile['default'])->toBeString()
            ->and($configFile['disks'])->toBeArray();
    });
});

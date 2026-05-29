<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime;

use Marko\Mcp\Tools\ToolDefinition;
use Marko\Mcp\Tools\ToolHandlerInterface;
use PDO;

readonly class AppInfoTool implements ToolHandlerInterface
{
    public function __construct(
        private string $composerJsonPath = '',
        private string $installedJsonPath = '',
    ) {}

    public static function definition(
        string $composerJsonPath = '',
        string $installedJsonPath = '',
    ): ToolDefinition {
        return new ToolDefinition(
            name: 'app_info',
            description: 'Return PHP version, Marko version, DB engine, and installed marko/* package versions',
            inputSchema: ['type' => 'object', 'properties' => []],
            handler: new self($composerJsonPath, $installedJsonPath),
        );
    }

    public function handle(array $arguments): array
    {
        $lines = [];
        $lines[] = 'PHP version: ' . PHP_VERSION;

        $markoVersion = $this->resolveMarkoVersion();
        $lines[] = 'Marko version: ' . $markoVersion;

        $dbEngine = $this->resolveDbEngine();

        if ($dbEngine !== null) {
            $lines[] = 'DB engine: ' . $dbEngine;
        }

        $packages = $this->resolveMarkoPackages();

        if ($packages !== []) {
            $lines[] = '';
            $lines[] = 'Installed marko/* packages:';

            foreach ($packages as $name => $version) {
                $lines[] = "  $name: $version";
            }
        }

        return ['content' => [['type' => 'text', 'text' => implode("\n", $lines)]]];
    }

    private function resolveMarkoVersion(): string
    {
        $path = $this->composerJsonPath !== '' ? $this->composerJsonPath : $this->findComposerJson();

        if ($path === null || ! file_exists($path)) {
            return 'unknown';
        }

        $data = json_decode((string) file_get_contents($path), associative: true);

        return (string) ($data['version'] ?? 'dev');
    }

    private function findComposerJson(): ?string
    {
        $dir = dirname(__DIR__, 4);

        for ($i = 0; $i < 5; $i++) {
            $candidate = $dir . '/composer.json';

            if (file_exists($candidate)) {
                return $candidate;
            }

            $dir = dirname($dir);
        }

        return null;
    }

    private function resolveDbEngine(): ?string
    {
        if (! extension_loaded('pdo')) {
            return null;
        }

        $drivers = PDO::getAvailableDrivers();

        return $drivers !== [] ? implode(', ', $drivers) : null;
    }

    /** @return array<string, string> */
    private function resolveMarkoPackages(): array
    {
        $path = $this->installedJsonPath !== ''
            ? $this->installedJsonPath
            : $this->findInstalledJson();

        if ($path === null || ! file_exists($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), associative: true);
        $packages = $data['packages'] ?? $data;

        if (! is_array($packages)) {
            return [];
        }

        $result = [];

        foreach ($packages as $package) {
            $name = (string) ($package['name'] ?? '');

            if (str_starts_with($name, 'marko/')) {
                $result[$name] = (string) ($package['version'] ?? 'unknown');
            }
        }

        ksort($result);

        return $result;
    }

    private function findInstalledJson(): ?string
    {
        $candidate = dirname(__DIR__, 5) . '/vendor/composer/installed.json';

        return file_exists($candidate) ? $candidate : null;
    }
}

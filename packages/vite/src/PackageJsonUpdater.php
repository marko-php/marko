<?php

declare(strict_types=1);

namespace Marko\Vite;

use JsonException;
use Marko\Core\Path\ProjectPaths;
use Marko\Vite\ValueObjects\PackageJsonUpdateResult;
use RuntimeException;

class PackageJsonUpdater
{
    public function __construct(
        private readonly ProjectPaths $paths,
    ) {}

    /**
     * @param array<string, scalar> $fields
     * @param array<string, string> $scripts
     * @param array<string, string> $devDependencies
     */
    public function update(
        array $fields = [],
        array $scripts = [],
        array $devDependencies = [],
        bool $force = false,
        bool $dryRun = false,
    ): PackageJsonUpdateResult {
        [$packageJson, $createdFile] = $this->loadPackageJson();

        $added = [];
        $alreadyPresent = [];
        $updated = [];
        $skipped = [];

        foreach ($fields as $key => $value) {
            $this->applyValue(
                data: $packageJson,
                section: null,
                key: $key,
                value: $value,
                label: sprintf('field `%s`', $key),
                force: $force,
                added: $added,
                alreadyPresent: $alreadyPresent,
                updated: $updated,
                skipped: $skipped,
            );
        }

        $this->ensureObject($packageJson, 'scripts');
        foreach ($scripts as $name => $command) {
            $this->applyValue(
                data: $packageJson,
                section: 'scripts',
                key: $name,
                value: $command,
                label: sprintf('script `%s`', $name),
                force: $force,
                added: $added,
                alreadyPresent: $alreadyPresent,
                updated: $updated,
                skipped: $skipped,
            );
        }

        $this->ensureObject($packageJson, 'devDependencies');
        foreach ($devDependencies as $name => $version) {
            $this->applyValue(
                data: $packageJson,
                section: 'devDependencies',
                key: $name,
                value: $version,
                label: sprintf('devDependency `%s`', $name),
                force: $force,
                added: $added,
                alreadyPresent: $alreadyPresent,
                updated: $updated,
                skipped: $skipped,
            );
        }

        $result = new PackageJsonUpdateResult(
            createdFile: $createdFile,
            added: $added,
            alreadyPresent: $alreadyPresent,
            updated: $updated,
            skipped: $skipped,
        );

        if (($createdFile || $result->changed()) && !$dryRun) {
            $this->writePackageJson($packageJson);
        }

        return $result;
    }

    /**
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function loadPackageJson(): array
    {
        $path = $this->packageJsonPath();

        if (!is_file($path)) {
            return [[
                'private' => true,
            ], true];
        }

        $contents = (string) file_get_contents($path);

        if (trim($contents) === '') {
            return [[
                'private' => true,
            ], true];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('Failed to parse package.json: %s', $exception->getMessage()),
                previous: $exception,
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Failed to parse package.json: expected a JSON object.');
        }

        return [$decoded, false];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string> $added
     * @param array<string> $alreadyPresent
     * @param array<string> $updated
     * @param array<string> $skipped
     */
    private function applyValue(
        array &$data,
        ?string $section,
        string $key,
        string|int|float|bool $value,
        string $label,
        bool $force,
        array &$added,
        array &$alreadyPresent,
        array &$updated,
        array &$skipped,
    ): void {
        if ($section === null) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
                $added[] = $label;

                return;
            }

            if ($data[$key] === $value) {
                $alreadyPresent[] = $label;

                return;
            }

            if ($force) {
                $data[$key] = $value;
                $updated[] = $label;

                return;
            }

            $skipped[] = sprintf('%s because it already exists with a different value', $label);

            return;
        }

        $sectionData = $data[$section];

        if (!is_array($sectionData)) {
            if ($force) {
                $sectionData = [];
                $updated[] = sprintf('section `%s`', $section);
            } else {
                $skipped[] = sprintf('%s because `%s` is not an object', $label, $section);

                return;
            }
        }

        if (!array_key_exists($key, $sectionData)) {
            $sectionData[$key] = $value;
            $data[$section] = $sectionData;
            $added[] = $label;

            return;
        }

        if ($sectionData[$key] === $value) {
            $alreadyPresent[] = $label;
            $data[$section] = $sectionData;

            return;
        }

        if ($force) {
            $sectionData[$key] = $value;
            $data[$section] = $sectionData;
            $updated[] = $label;

            return;
        }

        $skipped[] = sprintf('%s because it already exists with a different value', $label);
        $data[$section] = $sectionData;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensureObject(
        array &$data,
        string $key,
    ): void
    {
        if (!array_key_exists($key, $data)) {
            $data[$key] = [];

            return;
        }

        if (!is_array($data[$key])) {
            $data[$key] = [];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writePackageJson(array $data): void
    {
        $path = $this->packageJsonPath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        try {
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('Failed to encode package.json: %s', $exception->getMessage()),
                previous: $exception,
            );
        }

        file_put_contents($path, $encoded . PHP_EOL);
    }

    private function packageJsonPath(): string
    {
        return $this->paths->base . '/package.json';
    }
}

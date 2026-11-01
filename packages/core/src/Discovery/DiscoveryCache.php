<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Container\PreferenceRecord;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\CachedModule;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Support\ErrorCapture;

/**
 * Reads and writes the compiled discovery cache file.
 *
 * Besides the preferences, plugins, observers and commands found by attribute
 * discovery, the file holds the resolved module list (composer-derived fields,
 * paths relative to the project root), the global middleware order, one
 * section per DiscoveryCacheContributorInterface, and a fingerprint of the
 * installed packages and module directories so a stale file fails loudly.
 *
 * @phpstan-type CachePayload array{preferences: PreferenceRecord[], plugins: PluginDefinition[], observers: ObserverDefinition[], commands: CommandDefinition[], modules?: array<ModuleManifest|CachedModule>, globalMiddleware?: array<int, string>, sections?: array<string, array<mixed>>}
 * @phpstan-type LoadedCache array{preferences: PreferenceRecord[], plugins: PluginDefinition[], observers: ObserverDefinition[], commands: CommandDefinition[], modules: CachedModule[], globalMiddleware: array<int, string>, sections: array<string, array<mixed>>}
 */
class DiscoveryCache
{
    public const int CACHE_VERSION = 4;

    private const array REQUIRED_KEYS = [
        'fingerprint',
        'modules',
        'preferences',
        'plugins',
        'observers',
        'commands',
        'globalMiddleware',
        'sections',
    ];

    public function __construct(
        private readonly ProjectPaths $projectPaths,
        private readonly DiscoveryEnvironment $discoveryEnvironment,
        private readonly DiscoveryFingerprint $discoveryFingerprint = new DiscoveryFingerprint(),
    ) {}

    /**
     * Returns the absolute path to the cache file.
     *
     * A leading '/' (Unix) or a Windows drive letter (e.g. 'C:\') indicates an absolute path;
     * relative paths are resolved against the project base.
     */
    private function resolveCachePath(): string
    {
        $path = $this->discoveryEnvironment->cachePath();

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->projectPaths->base . '/' . $path;
    }

    private function isAbsolutePath(
        string $path,
    ): bool {
        return str_starts_with($path, '/') || (strlen($path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':');
    }

    /**
     * Returns the absolute path to the cache file (public accessor).
     */
    public function path(): string
    {
        return $this->resolveCachePath();
    }

    /**
     * Returns true if the cache file currently exists.
     */
    public function exists(): bool
    {
        return file_exists($this->resolveCachePath());
    }

    /**
     * Removes the cache file if it exists. Idempotent — safe to call when absent.
     */
    public function clear(): void
    {
        $path = $this->resolveCachePath();
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Serializes the discovery payload to a PHP return-array file using var_export.
     *
     * Writes atomically via a temp file in the same directory, then rename().
 * The file is made readable and writable by its owner only (0600), because
 * contributor sections can hold resolved secrets.
     *
     * The fingerprint of the current project is computed and stored with the payload.
     *
     * @param CachePayload $payload
     *
     * @throws DiscoveryCacheException
     */
    public function write(
        array $payload,
    ): void {
        $path = $this->resolveCachePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            if (!ErrorCapture::run($reason, fn (): bool => mkdir($dir, 0755, true)) && !is_dir($dir)) {
                throw DiscoveryCacheException::notWritable($path, $reason);
            }
        }

        $data = [
            'version' => self::CACHE_VERSION,
            'fingerprint' => $this->discoveryFingerprint->compute($this->projectPaths),
            'modules' => array_map(
                fn (ModuleManifest|CachedModule $module): array => $this->exportModule($module),
                array_values($payload['modules'] ?? []),
            ),
            'globalMiddleware' => array_values($payload['globalMiddleware'] ?? []),
            'sections' => $payload['sections'] ?? [],
            'preferences' => array_map(
                fn (PreferenceRecord $r) => [
                    'replacement' => $r->replacement,
                    'replaces' => $r->replaces,
                ],
                $payload['preferences'],
            ),
            'plugins' => array_map(
                fn (PluginDefinition $d) => [
                    'pluginClass' => $d->pluginClass,
                    'targetClass' => $d->targetClass,
                    'beforeMethods' => $d->beforeMethods,
                    'afterMethods' => $d->afterMethods,
                ],
                $payload['plugins'],
            ),
            'observers' => array_map(
                fn (ObserverDefinition $d) => [
                    'observerClass' => $d->observerClass,
                    'eventClass' => $d->eventClass,
                    'priority' => $d->priority,
                    'async' => $d->async,
                ],
                $payload['observers'],
            ),
            'commands' => array_map(
                fn (CommandDefinition $d) => [
                    'commandClass' => $d->commandClass,
                    'name' => $d->name,
                    'description' => $d->description,
                    'aliases' => $d->aliases,
                    'flags' => $d->flags,
                    'destructive' => $d->destructive,
                ],
                $payload['commands'],
            ),
        ];

        $content = "<?php\n\n// This file is generated by Marko — do not edit. Run vendor/bin/marko discovery:clear to remove.\n\nreturn " . var_export(
            $data,
            true,
        ) . ";\n";

        $tmp = $this->tempPath($dir);

        if (ErrorCapture::run($reason, fn (): int|false => file_put_contents($tmp, $content)) === false) {
            throw DiscoveryCacheException::notWritable($path, $reason);
        }

        // Sections can hold resolved secrets (the config section holds database passwords and app keys)
        if (!ErrorCapture::run($reason, fn (): bool => chmod($tmp, 0600))) {
            @unlink($tmp);

            throw DiscoveryCacheException::notWritable($path, $reason);
        }

        if (!ErrorCapture::run($reason, fn (): bool => rename($tmp, $path))) {
            @unlink($tmp);

            throw DiscoveryCacheException::notWritable($path, $reason);
        }
    }

    /**
     * Loads and hydrates the cache file into typed value objects.
     *
     * The file is executable PHP, so before including it the file and its
     * directory must not be world-writable and must be owned by the user
     * running PHP (or root). Otherwise another local user could plant code.
     *
     * Checks, in order: the file returns an array, its version matches, every
     * section is well-formed, and its fingerprint matches the current project
     * (a mismatch means packages or modules changed since it was compiled).
     *
     * @return LoadedCache
     *
     * @throws DiscoveryCacheException
     */
    public function load(): array
    {
        $path = $this->resolveCachePath();

        if (!file_exists($path)) {
            throw DiscoveryCacheException::unreadable($path);
        }

        $this->assertTrusted($path);
        $this->assertTrusted(dirname($path));

        /** @var mixed $data */
        $data = include $path;

        if (!is_array($data)) {
            throw DiscoveryCacheException::malformed($path, 'cache file must return an array');
        }

        if (!array_key_exists('version', $data)) {
            throw DiscoveryCacheException::malformed($path, "missing required key 'version'");
        }

        if ($data['version'] !== self::CACHE_VERSION) {
            throw DiscoveryCacheException::versionMismatch($path, (int) $data['version'], self::CACHE_VERSION);
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $data)) {
                throw DiscoveryCacheException::malformed($path, "missing required key '$key'");
            }
        }

        $loaded = [
            'preferences' => $this->hydratePreferences($path, $data['preferences']),
            'plugins' => $this->hydratePlugins($path, $data['plugins']),
            'observers' => $this->hydrateObservers($path, $data['observers']),
            'commands' => $this->hydrateCommands($path, $data['commands']),
            'modules' => $this->hydrateModules($path, $data['modules']),
            'globalMiddleware' => $this->hydrateGlobalMiddleware($path, $data['globalMiddleware']),
            'sections' => $this->hydrateSections($path, $data['sections']),
        ];

        if ($data['fingerprint'] !== $this->discoveryFingerprint->compute($this->projectPaths)) {
            throw DiscoveryCacheException::stale(
                $path,
                'installed packages (vendor/composer/installed.json), the module directories under modules/ and app/, or the root config/*.php files changed since it was compiled',
            );
        }

        return $loaded;
    }

    /**
     * Returns an unguessable temp file name in the cache directory, so another
     * local user cannot pre-create it (or a symlink at it) before the write.
     */
    protected function tempPath(
        string $dir,
    ): string {
        return $dir . '/.discovery_cache_' . bin2hex(random_bytes(8)) . '.tmp';
    }

    /**
     * Returns the effective uid of the running process, or null where it
     * cannot be determined (Windows, or the posix extension is missing), in
     * which case the ownership check is skipped.
     */
    protected function currentUid(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('posix_geteuid')) {
            return null;
        }

        return posix_geteuid();
    }

    /**
     * Refuses a cache file or directory that someone other than the running
     * user could have written to.
     *
     * @throws DiscoveryCacheException
     */
    private function assertTrusted(
        string $target,
    ): void {
        clearstatcache(true, $target);
        $perms = ErrorCapture::run($reason, fn (): int|false => fileperms($target));

        if ($perms === false) {
            throw DiscoveryCacheException::untrusted(
                $target,
                'its permissions could not be read' . ($reason !== null ? ": $reason" : ''),
            );
        }

        if (($perms & 0002) !== 0) {
            throw DiscoveryCacheException::untrusted($target, 'it is world-writable');
        }

        $uid = $this->currentUid();

        if ($uid === null) {
            return;
        }

        $owner = ErrorCapture::run($reason, fn (): int|false => fileowner($target));

        if ($owner === false) {
            throw DiscoveryCacheException::untrusted(
                $target,
                'its owner could not be read' . ($reason !== null ? ": $reason" : ''),
            );
        }

        if ($owner !== $uid && $owner !== 0) {
            throw DiscoveryCacheException::untrusted(
                $target,
                "it is owned by uid $owner, not by uid $uid running PHP (or root)",
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function exportModule(
        ModuleManifest|CachedModule $module,
    ): array {
        $module = $module instanceof ModuleManifest ? CachedModule::fromManifest($module) : $module;
        $prefix = $this->projectPaths->base . '/';

        return [
            'name' => $module->name,
            'version' => $module->version,
            'path' => str_starts_with($module->path, $prefix) ? substr($module->path, strlen($prefix)) : $module->path,
            'source' => $module->source,
            'require' => $module->require,
            'autoload' => $module->autoload,
            'extra' => $module->extra,
            'after' => $module->after,
            'before' => $module->before,
            'globalMiddleware' => $module->globalMiddleware,
        ];
    }

    /**
     * @return CachedModule[]
     *
     * @throws DiscoveryCacheException
     */
    private function hydrateModules(
        string $path,
        mixed $records,
    ): array {
        if (!is_array($records)) {
            throw DiscoveryCacheException::malformed($path, "'modules' must be an array");
        }

        $result = [];
        foreach ($records as $i => $record) {
            if (!is_array($record)) {
                throw DiscoveryCacheException::malformed($path, "modules[$i] must be an array");
            }
            $this->assertStringField($path, $record, 'modules', $i, 'name');
            $this->assertStringField($path, $record, 'modules', $i, 'version');
            $this->assertStringField($path, $record, 'modules', $i, 'path');
            $this->assertStringField($path, $record, 'modules', $i, 'source');
            $this->assertArrayField($path, $record, 'modules', $i, 'require');
            $this->assertArrayField($path, $record, 'modules', $i, 'autoload');
            $this->assertArrayField($path, $record, 'modules', $i, 'extra');
            $this->assertArrayField($path, $record, 'modules', $i, 'after');
            $this->assertArrayField($path, $record, 'modules', $i, 'before');
            $this->assertArrayField($path, $record, 'modules', $i, 'globalMiddleware');

            $result[] = new CachedModule(
                name: $record['name'],
                version: $record['version'],
                path: $this->isAbsolutePath($record['path'])
                    ? $record['path']
                    : $this->projectPaths->base . '/' . $record['path'],
                source: $record['source'],
                require: $record['require'],
                autoload: $record['autoload'],
                extra: $record['extra'],
                after: $record['after'],
                before: $record['before'],
                globalMiddleware: $record['globalMiddleware'],
            );
        }

        return $result;
    }

    /**
     * @return array<int, string>
     *
     * @throws DiscoveryCacheException
     */
    private function hydrateGlobalMiddleware(
        string $path,
        mixed $classes,
    ): array {
        if (!is_array($classes)) {
            throw DiscoveryCacheException::malformed($path, "'globalMiddleware' must be an array");
        }

        foreach ($classes as $i => $class) {
            if (!is_string($class)) {
                throw DiscoveryCacheException::malformed($path, "globalMiddleware[$i] must be a string");
            }
        }

        return array_values($classes);
    }

    /**
     * @return array<string, array<mixed>>
     *
     * @throws DiscoveryCacheException
     */
    private function hydrateSections(
        string $path,
        mixed $sections,
    ): array {
        if (!is_array($sections)) {
            throw DiscoveryCacheException::malformed($path, "'sections' must be an array");
        }

        foreach ($sections as $key => $section) {
            if (!is_string($key) || !is_array($section)) {
                throw DiscoveryCacheException::malformed($path, "sections.$key must be an array");
            }
        }

        return $sections;
    }

    /**
     * @param mixed $records
     * @return PreferenceRecord[]
     *
     * @throws DiscoveryCacheException
     */
    private function hydratePreferences(
        string $path,
        mixed $records,
    ): array {
        if (!is_array($records)) {
            throw DiscoveryCacheException::malformed($path, "'preferences' must be an array");
        }

        $result = [];
        foreach ($records as $i => $record) {
            if (!is_array($record)) {
                throw DiscoveryCacheException::malformed($path, "preferences[$i] must be an array");
            }
            $this->assertStringField($path, $record, 'preferences', $i, 'replacement');
            $this->assertStringField($path, $record, 'preferences', $i, 'replaces');

            $result[] = new PreferenceRecord(
                replacement: $record['replacement'],
                replaces: $record['replaces'],
            );
        }

        return $result;
    }

    /**
     * @param mixed $records
     * @return PluginDefinition[]
     *
     * @throws DiscoveryCacheException
     */
    private function hydratePlugins(
        string $path,
        mixed $records,
    ): array {
        if (!is_array($records)) {
            throw DiscoveryCacheException::malformed($path, "'plugins' must be an array");
        }

        $result = [];
        foreach ($records as $i => $record) {
            if (!is_array($record)) {
                throw DiscoveryCacheException::malformed($path, "plugins[$i] must be an array");
            }
            $this->assertStringField($path, $record, 'plugins', $i, 'pluginClass');
            $this->assertStringField($path, $record, 'plugins', $i, 'targetClass');
            $this->assertArrayField($path, $record, 'plugins', $i, 'beforeMethods');
            $this->assertArrayField($path, $record, 'plugins', $i, 'afterMethods');

            $result[] = new PluginDefinition(
                pluginClass: $record['pluginClass'],
                targetClass: $record['targetClass'],
                beforeMethods: $record['beforeMethods'],
                afterMethods: $record['afterMethods'],
            );
        }

        return $result;
    }

    /**
     * @param mixed $records
     * @return ObserverDefinition[]
     *
     * @throws DiscoveryCacheException
     */
    private function hydrateObservers(
        string $path,
        mixed $records,
    ): array {
        if (!is_array($records)) {
            throw DiscoveryCacheException::malformed($path, "'observers' must be an array");
        }

        $result = [];
        foreach ($records as $i => $record) {
            if (!is_array($record)) {
                throw DiscoveryCacheException::malformed($path, "observers[$i] must be an array");
            }
            $this->assertStringField($path, $record, 'observers', $i, 'observerClass');
            $this->assertStringField($path, $record, 'observers', $i, 'eventClass');
            $this->assertIntField($path, $record, 'observers', $i, 'priority');
            $this->assertBoolField($path, $record, 'observers', $i, 'async');

            $result[] = new ObserverDefinition(
                observerClass: $record['observerClass'],
                eventClass: $record['eventClass'],
                priority: $record['priority'],
                async: $record['async'],
            );
        }

        return $result;
    }

    /**
     * @param mixed $records
     * @return CommandDefinition[]
     *
     * @throws DiscoveryCacheException
     */
    private function hydrateCommands(
        string $path,
        mixed $records,
    ): array {
        if (!is_array($records)) {
            throw DiscoveryCacheException::malformed($path, "'commands' must be an array");
        }

        $result = [];
        foreach ($records as $i => $record) {
            if (!is_array($record)) {
                throw DiscoveryCacheException::malformed($path, "commands[$i] must be an array");
            }
            $this->assertStringField($path, $record, 'commands', $i, 'commandClass');
            $this->assertStringField($path, $record, 'commands', $i, 'name');
            $this->assertStringField($path, $record, 'commands', $i, 'description');
            $this->assertArrayField($path, $record, 'commands', $i, 'aliases');
            $this->assertArrayField($path, $record, 'commands', $i, 'flags');
            $this->assertBoolField($path, $record, 'commands', $i, 'destructive');

            $result[] = new CommandDefinition(
                commandClass: $record['commandClass'],
                name: $record['name'],
                description: $record['description'],
                aliases: $record['aliases'],
                flags: $record['flags'],
                destructive: $record['destructive'],
            );
        }

        return $result;
    }

    /**
     * @param array<mixed> $record
     *
     * @throws DiscoveryCacheException
     */
    private function assertStringField(
        string $path,
        array $record,
        string $section,
        int|string $index,
        string $field,
    ): void {
        if (!array_key_exists($field, $record)) {
            throw DiscoveryCacheException::malformed($path, $section . "[$index] missing required field '$field'");
        }
        if (!is_string($record[$field])) {
            throw DiscoveryCacheException::malformed($path, $section . "[$index].$field must be a string");
        }
    }

    /**
     * @param array<mixed> $record
     *
     * @throws DiscoveryCacheException
     */
    private function assertArrayField(
        string $path,
        array $record,
        string $section,
        int|string $index,
        string $field,
    ): void {
        if (!array_key_exists($field, $record)) {
            throw DiscoveryCacheException::malformed($path, $section . "[$index] missing required field '$field'");
        }
        if (!is_array($record[$field])) {
            throw DiscoveryCacheException::malformed($path, $section . "[$index].$field must be an array");
        }
    }

    /**
     * @param array<mixed> $record
     *
     * @throws DiscoveryCacheException
     */
    private function assertIntField(
        string $path,
        array $record,
        string $section,
        int|string $index,
        string $field,
    ): void {
        if (!array_key_exists($field, $record)) {
            throw DiscoveryCacheException::malformed($path, $section . "[$index] missing required field '$field'");
        }
        if (!is_int($record[$field])) {
            throw DiscoveryCacheException::malformed($path, $section . "[$index].$field must be an int");
        }
    }

    /**
     * @param array<mixed> $record
     *
     * @throws DiscoveryCacheException
     */
    private function assertBoolField(
        string $path,
        array $record,
        string $section,
        int|string $index,
        string $field,
    ): void {
        if (!array_key_exists($field, $record)) {
            throw DiscoveryCacheException::malformed($path, $section . "[$index] missing required field '$field'");
        }
        if (!is_bool($record[$field])) {
            throw DiscoveryCacheException::malformed($path, $section . "[$index].$field must be a bool");
        }
    }
}

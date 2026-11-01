<?php

declare(strict_types=1);

namespace Marko\Authorization\Discovery;

use Marko\Authorization\Attributes\GateHook;
use Marko\Authorization\Contracts\GateHookInterface;
use Marko\Authorization\Exceptions\GateHookException;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use ReflectionClass;

/**
 * Discovers #[GateHook] classes in every module's src/ directory.
 */
readonly class GateHookDiscovery
{
    public function __construct(
        private ClassFileParser $classFileParser = new ClassFileParser(),
    ) {}

    /**
     * Every #[GateHook] class across the given modules, highest priority first.
     *
     * Hooks with the same priority keep module load order, then file order.
     *
     * @param array<ModuleManifest> $modules
     * @return array<int, GateHookDefinition>
     * @throws GateHookException
     */
    public function discoverAll(
        array $modules,
    ): array {
        $definitions = [];

        foreach ($modules as $module) {
            foreach ($this->discoverInModule($module) as $definition) {
                $definitions[] = $definition;
            }
        }

        usort(
            $definitions,
            fn (GateHookDefinition $a, GateHookDefinition $b): int => $b->priority <=> $a->priority,
        );

        return $definitions;
    }

    /**
     * Every #[GateHook] class in one module's src/ directory, in file order.
     *
     * A cheap text match selects candidate files (they must contain "#[" and mention
     * "GateHook"); each class a candidate declares is then loaded and confirmed with
     * reflection, so a mention in a comment or a longer attribute name is skipped.
     *
     * @return array<int, GateHookDefinition>
     * @throws GateHookException
     */
    public function discoverInModule(
        ModuleManifest $module,
    ): array {
        $srcPath = $module->path . '/src';

        if (!is_dir($srcPath)) {
            return [];
        }

        $definitions = [];

        foreach ($this->classFileParser->findPhpFiles($srcPath) as $file) {
            $filePath = $file->getPathname();
            $content = file_get_contents($filePath);

            if ($content === false || !str_contains($content, '#[') || stripos($content, 'GateHook') === false) {
                continue;
            }

            foreach ($this->classFileParser->extractClassNames($filePath) as $className) {
                $definition = $this->definitionFor($filePath, $className);

                if ($definition !== null) {
                    $definitions[] = $definition;
                }
            }
        }

        return $definitions;
    }

    /**
     * @throws GateHookException
     */
    private function definitionFor(
        string $filePath,
        string $className,
    ): ?GateHookDefinition {
        if (!$this->classFileParser->loadClass($filePath, $className)) {
            return null;
        }

        /** @var class-string $className */
        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(GateHook::class);

        if ($attributes === []) {
            return null;
        }

        if (!$reflection->implementsInterface(GateHookInterface::class)) {
            throw GateHookException::notAHook($className);
        }

        /** @var class-string<GateHookInterface> $className */
        return new GateHookDefinition(
            className: $className,
            priority: $attributes[0]->newInstance()->priority,
        );
    }
}

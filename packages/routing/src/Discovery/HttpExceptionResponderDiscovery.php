<?php

declare(strict_types=1);

namespace Marko\Routing\Discovery;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Attributes\HttpExceptionResponder;
use Marko\Routing\Exceptions\HttpExceptionResponderException;
use Marko\Routing\Http\HttpExceptionResponderInterface;
use ReflectionClass;

/**
 * Discovers #[HttpExceptionResponder] classes in every module's src/ directory.
 */
readonly class HttpExceptionResponderDiscovery
{
    public function __construct(
        private ClassFileParser $classFileParser = new ClassFileParser(),
    ) {}

    /**
     * Every #[HttpExceptionResponder] class across the given modules, highest
     * priority first.
     *
     * Responders with the same priority keep module load order, then file order.
     *
     * @param array<ModuleManifest> $modules
     * @return array<int, HttpExceptionResponderDefinition>
     * @throws HttpExceptionResponderException
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
            fn (HttpExceptionResponderDefinition $a, HttpExceptionResponderDefinition $b): int => $b->priority <=> $a->priority,
        );

        return $definitions;
    }

    /**
     * Every #[HttpExceptionResponder] class in one module's src/ directory, in file order.
     *
     * A cheap text match selects candidate files (they must contain "#[" and
     * mention "HttpExceptionResponder"); each class a candidate declares is then
     * loaded and confirmed with reflection, so a mention in a comment or a
     * longer attribute name is skipped.
     *
     * @return array<int, HttpExceptionResponderDefinition>
     * @throws HttpExceptionResponderException
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

            if (
                $content === false
                || !str_contains($content, '#[')
                || !str_contains($content, 'HttpExceptionResponder')
            ) {
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
     * @throws HttpExceptionResponderException
     */
    private function definitionFor(
        string $filePath,
        string $className,
    ): ?HttpExceptionResponderDefinition {
        if (!$this->classFileParser->loadClass($filePath, $className)) {
            return null;
        }

        /** @var class-string $className */
        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(HttpExceptionResponder::class);

        if ($attributes === []) {
            return null;
        }

        if (!$reflection->implementsInterface(HttpExceptionResponderInterface::class)) {
            throw HttpExceptionResponderException::notAResponder($className);
        }

        /** @var class-string<HttpExceptionResponderInterface> $className */
        return new HttpExceptionResponderDefinition(
            className: $className,
            priority: $attributes[0]->newInstance()->priority,
        );
    }
}

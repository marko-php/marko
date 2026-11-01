<?php

declare(strict_types=1);

namespace Marko\View\Discovery;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\View\Attributes\TemplateFunctions;
use Marko\View\Attributes\ViewData;
use Marko\View\Exceptions\ViewProviderException;
use Marko\View\TemplateFunctionProviderInterface;
use Marko\View\ViewDataProviderInterface;
use ReflectionClass;

/**
 * Finds #[TemplateFunctions] and #[ViewData] provider classes in every module's src/ directory.
 */
readonly class ViewProviderDiscovery
{
    public function __construct(
        private ClassFileParser $classFileParser = new ClassFileParser(),
    ) {}

    /**
     * @param array<ModuleManifest> $modules Enabled modules in load order
     * @throws ViewProviderException When a marked class does not implement its provider interface
     */
    public function discover(
        array $modules,
    ): ViewProviderClasses {
        $templateFunctionProviders = [];
        $viewDataProviders = [];

        foreach ($modules as $module) {
            foreach ($this->classFileParser->findPhpFiles($module->path . '/src') as $file) {
                $filePath = $file->getPathname();

                if (!$this->mayDeclareProvider($filePath)) {
                    continue;
                }

                foreach ($this->classFileParser->extractClassNames($filePath) as $className) {
                    if (!$this->classFileParser->loadClass($filePath, $className)) {
                        continue;
                    }

                    /** @var class-string $className */
                    $reflection = new ReflectionClass($className);

                    if ($this->isMarked(
                        $reflection,
                        TemplateFunctions::class,
                        TemplateFunctionProviderInterface::class,
                    )) {
                        /** @var class-string<TemplateFunctionProviderInterface> $className */
                        $templateFunctionProviders[] = $className;
                    }

                    if ($this->isMarked($reflection, ViewData::class, ViewDataProviderInterface::class)) {
                        /** @var class-string<ViewDataProviderInterface> $className */
                        $viewDataProviders[] = $className;
                    }
                }
            }
        }

        return new ViewProviderClasses($templateFunctionProviders, $viewDataProviders);
    }

    /**
     * A cheap text match: a provider file contains an attribute and names one of the provider attributes.
     */
    private function mayDeclareProvider(
        string $filePath,
    ): bool {
        $content = file_get_contents($filePath);

        return $content !== false
            && str_contains($content, '#[')
            && (stripos($content, 'TemplateFunctions') !== false || stripos($content, 'ViewData') !== false);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param class-string $attribute
     * @param class-string $interface
     * @throws ViewProviderException
     */
    private function isMarked(
        ReflectionClass $reflection,
        string $attribute,
        string $interface,
    ): bool {
        if ($reflection->getAttributes($attribute) === []) {
            return false;
        }

        if (!$reflection->implementsInterface($interface)) {
            throw ViewProviderException::notAProvider($reflection->getName(), $attribute, $interface);
        }

        return true;
    }
}

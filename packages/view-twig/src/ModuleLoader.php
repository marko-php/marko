<?php

declare(strict_types=1);

namespace Marko\View\Twig;

use Marko\View\Exceptions\TemplateNotFoundException;
use Marko\View\TemplateResolverInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Twig loader that resolves module-namespaced templates.
 *
 * Templates use the format: module::path/to/template
 * Example: blog::post/list/item
 */
class ModuleLoader implements LoaderInterface
{
    public function __construct(
        private TemplateResolverInterface $resolver,
    ) {}

    /**
     * @throws LoaderError|TemplateNotFoundException
     */
    public function getSourceContext(string $name): Source
    {
        $this->assertModuleNamespacedFormat($name);

        $path = $this->resolvePath($name);

        if (!is_readable($path)) {
            throw new LoaderError("Unable to read template file: '$path'.");
        }

        $code = file_get_contents($path);

        if ($code === false) {
            throw new LoaderError("Unable to read template file: '$path'.");
        }

        return new Source($code, $name, $path);
    }

    /**
     * @throws LoaderError|TemplateNotFoundException
     */
    public function getCacheKey(string $name): string
    {
        $this->assertModuleNamespacedFormat($name);

        return $this->resolvePath($name);
    }

    /**
     * @throws TemplateNotFoundException When template cannot be found
     */
    public function isFresh(
        string $name,
        int $time,
    ): bool {
        $path = $this->resolvePath($name);

        return filemtime($path) <= $time;
    }

    public function exists(string $name): bool
    {
        if (!str_contains($name, '::')) {
            return false;
        }

        try {
            $this->resolvePath($name);

            return true;
        } catch (TemplateNotFoundException) {
            return false;
        }
    }

    private function assertModuleNamespacedFormat(string $name): void
    {
        if (!str_contains($name, '::')) {
            throw new LoaderError(
                "Template includes must use module namespace format (e.g., 'blog::post/list/item'). Got '$name'.",
            );
        }
    }

    private function resolvePath(string $name): string
    {
        return $this->resolver->resolve($name);
    }
}

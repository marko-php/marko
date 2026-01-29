<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Latte\Loader;
use Latte\RuntimeException;
use Marko\View\TemplateResolverInterface;

/**
 * Latte loader that resolves module-namespaced templates.
 *
 * Templates use the format: module::path/to/template
 * Example: blog::post/list/item
 */
class ModuleLoader implements Loader
{
    public function __construct(
        private TemplateResolverInterface $resolver,
    ) {}

    public function getContent(
        string $name,
    ): string {
        $path = $this->resolvePath($name);

        if (!is_file($path)) {
            throw new RuntimeException("Template file not found: '$path' (resolved from '$name')");
        }

        return file_get_contents($path);
    }

    public function getReferredName(
        string $name,
        string $referringName,
    ): string {
        // If the name already contains ::, it's a fully qualified module template
        if (str_contains($name, '::')) {
            return $name;
        }

        // Relative paths are not supported - all includes must use module::path format
        throw new RuntimeException(
            "Template includes must use module namespace format (e.g., 'blog::post/list/item'). " .
            "Got '$name' included from '$referringName'.",
        );
    }

    public function getUniqueId(
        string $name,
    ): string {
        // Use the resolved absolute path as the unique ID for caching
        return $this->resolvePath($name);
    }

    private function resolvePath(
        string $name,
    ): string {
        return $this->resolver->resolve($name);
    }
}

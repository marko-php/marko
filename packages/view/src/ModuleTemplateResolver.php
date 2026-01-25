<?php

declare(strict_types=1);

namespace Marko\View;

use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\View\Exceptions\TemplateNotFoundException;

readonly class ModuleTemplateResolver implements TemplateResolverInterface
{
    public function __construct(
        private ModuleRepositoryInterface $moduleRepository,
        private ViewConfig $viewConfig,
    ) {}

    public function resolve(
        string $template,
    ): string {
        $searchedPaths = $this->getSearchedPaths($template);

        foreach ($searchedPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw TemplateNotFoundException::forTemplate($template, $searchedPaths);
    }

    public function getSearchedPaths(
        string $template,
    ): array {
        [$moduleName, $templatePath] = $this->parseTemplate($template);
        $extension = $this->viewConfig->extension();

        $paths = [];

        foreach ($this->moduleRepository->all() as $module) {
            if ($this->matchesModuleName($module->name, $moduleName)) {
                $fullPath = $module->path . '/resources/views/' . $templatePath . $extension;
                $paths[] = $fullPath;
            }
        }

        return $paths;
    }

    /**
     * Parse template name into module name and path.
     *
     * @return array{0: string, 1: string} [moduleName, templatePath]
     */
    private function parseTemplate(
        string $template,
    ): array {
        if (str_contains($template, '::')) {
            [$moduleName, $templatePath] = explode('::', $template, 2);

            return [$moduleName, $templatePath];
        }

        return ['', $template];
    }

    /**
     * Check if a full module name matches a short module name.
     * 'vendor/blog' matches 'blog', 'marko/core' matches 'core'
     * Empty shortName matches all modules (template without module prefix)
     */
    private function matchesModuleName(
        string $fullName,
        string $shortName,
    ): bool {
        if ($shortName === '') {
            return true;
        }

        if ($fullName === $shortName) {
            return true;
        }

        $parts = explode('/', $fullName);

        return end($parts) === $shortName;
    }
}

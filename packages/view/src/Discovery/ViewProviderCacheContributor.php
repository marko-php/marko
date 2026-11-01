<?php

declare(strict_types=1);

namespace Marko\View\Discovery;

use Marko\Core\Discovery\DiscoveryCacheContributorInterface;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;
use Marko\View\Exceptions\ViewProviderException;
use Marko\View\TemplateFunctionProviderInterface;
use Marko\View\ViewDataProviderInterface;

/**
 * Stores the discovered template function and view data provider class names
 * in the discovery cache, so a cached boot finds them without scanning modules.
 */
readonly class ViewProviderCacheContributor implements DiscoveryCacheContributorInterface
{
    public const string KEY = 'view_providers';

    private const string TEMPLATE_FUNCTIONS = 'template_functions';

    private const string VIEW_DATA = 'view_data';

    public function __construct(
        private ViewProviderDiscovery $viewProviderDiscovery,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return array{template_functions: list<string>, view_data: list<string>}
     * @throws ViewProviderException
     */
    public function compile(
        array $modules,
    ): array {
        $classes = $this->viewProviderDiscovery->discover($modules);

        return [
            self::TEMPLATE_FUNCTIONS => $classes->templateFunctionProviders,
            self::VIEW_DATA => $classes->viewDataProviders,
        ];
    }

    /**
     * Rebuild the provider class lists from a cached section.
     *
     * @param array<mixed> $section
     * @throws DiscoveryCacheException
     */
    public function hydrate(
        array $section,
    ): ViewProviderClasses {
        /** @var list<class-string<TemplateFunctionProviderInterface>> $templateFunctionProviders */
        $templateFunctionProviders = $this->classList($section, self::TEMPLATE_FUNCTIONS);
        /** @var list<class-string<ViewDataProviderInterface>> $viewDataProviders */
        $viewDataProviders = $this->classList($section, self::VIEW_DATA);

        return new ViewProviderClasses($templateFunctionProviders, $viewDataProviders);
    }

    /**
     * @param array<mixed> $section
     * @return list<string>
     * @throws DiscoveryCacheException
     */
    private function classList(
        array $section,
        string $field,
    ): array {
        $classes = $section[$field] ?? null;

        if (
            !is_array($classes)
            || !array_is_list($classes)
            || !array_all($classes, fn (mixed $class): bool => is_string($class))
        ) {
            throw DiscoveryCacheException::malformedSection(self::KEY, "$field must be a list of class names");
        }

        /** @var list<string> $classes */
        return $classes;
    }
}

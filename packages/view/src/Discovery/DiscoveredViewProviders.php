<?php

declare(strict_types=1);

namespace Marko\View\Discovery;

use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\View\Exceptions\ViewProviderException;

/**
 * The template function and view data provider classes for this boot.
 *
 * A boot from the discovery cache reads them from the 'view_providers'
 * section; any other boot scans the enabled modules. Either way the work
 * happens once, on the first call to classes().
 */
class DiscoveredViewProviders
{
    private ?ViewProviderClasses $classes = null;

    public function __construct(
        private readonly CachedDiscovery $cachedDiscovery,
        private readonly ViewProviderCacheContributor $viewProviderCacheContributor,
        private readonly ViewProviderDiscovery $viewProviderDiscovery,
        private readonly ModuleRepositoryInterface $moduleRepository,
    ) {}

    /**
     * @throws DiscoveryCacheException|ViewProviderException
     */
    public function classes(): ViewProviderClasses
    {
        if ($this->classes === null) {
            $section = $this->cachedDiscovery->section(ViewProviderCacheContributor::KEY);

            $this->classes = $section !== null
                ? $this->viewProviderCacheContributor->hydrate($section)
                : $this->viewProviderDiscovery->discover($this->moduleRepository->all());
        }

        return $this->classes;
    }
}

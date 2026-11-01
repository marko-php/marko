<?php

declare(strict_types=1);

namespace Marko\View;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\View\Discovery\DiscoveredViewProviders;
use Marko\View\Exceptions\ViewProviderException;

/**
 * Merges the shared data of every discovered view data provider into a render.
 *
 * Every view driver calls merge() before rendering, so shared variables work
 * the same in every engine. Providers are resolved once; their data() is
 * called on every render.
 */
class ViewDataMerger
{
    /** @var array<string, ViewDataProviderInterface>|null provider instances keyed by discovered class */
    private ?array $providers = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DiscoveredViewProviders $discoveredViewProviders,
    ) {}

    /**
     * Add the provider data to the render data. Data passed to render() wins over provider data.
     *
     * @param array<string, mixed> $data Data passed to render()
     * @return array<string, mixed>
     * @throws ViewProviderException|DiscoveryCacheException When two providers supply the same key
     */
    public function merge(
        array $data,
    ): array {
        /** @var array<string, string> $owners data key => provider class */
        $owners = [];
        $shared = [];

        foreach ($this->resolvedProviders() as $providerClass => $provider) {
            foreach ($provider->data() as $key => $value) {
                if (isset($owners[$key])) {
                    throw ViewProviderException::duplicateDataKey((string) $key, $owners[$key], $providerClass);
                }

                $owners[$key] = $providerClass;
                $shared[$key] = $value;
            }
        }

        return array_replace($shared, $data);
    }

    /**
     * @return array<string, ViewDataProviderInterface>
     * @throws DiscoveryCacheException|ViewProviderException
     */
    private function resolvedProviders(): array
    {
        if ($this->providers === null) {
            $this->providers = [];

            foreach ($this->discoveredViewProviders->classes()->viewDataProviders as $providerClass) {
                /** @var ViewDataProviderInterface $provider */
                $provider = $this->container->get($providerClass);
                $this->providers[$providerClass] = $provider;
            }
        }

        return $this->providers;
    }
}

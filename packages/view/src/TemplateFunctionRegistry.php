<?php

declare(strict_types=1);

namespace Marko\View;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\View\Discovery\DiscoveredViewProviders;
use Marko\View\Exceptions\ViewProviderException;

/**
 * Every template function supplied by the discovered providers.
 *
 * Engine adapters read all() once, when they build the engine, and register
 * each function with it. Providers are resolved through the container, so a
 * Preference on a provider replaces its functions.
 */
class TemplateFunctionRegistry
{
    /** @var list<TemplateFunction>|null */
    private ?array $functions = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DiscoveredViewProviders $discoveredViewProviders,
    ) {}

    /**
     * @return list<TemplateFunction>
     * @throws ViewProviderException|DiscoveryCacheException When two providers register the same function name
     */
    public function all(): array
    {
        if ($this->functions !== null) {
            return $this->functions;
        }

        /** @var array<string, string> $owners function name => provider class */
        $owners = [];
        $functions = [];

        foreach ($this->discoveredViewProviders->classes()->templateFunctionProviders as $providerClass) {
            /** @var TemplateFunctionProviderInterface $provider */
            $provider = $this->container->get($providerClass);

            foreach ($provider->functions() as $function) {
                if (isset($owners[$function->name])) {
                    throw ViewProviderException::duplicateFunction(
                        $function->name,
                        $owners[$function->name],
                        $providerClass,
                    );
                }

                $owners[$function->name] = $providerClass;
                $functions[] = $function;
            }
        }

        return $this->functions = $functions;
    }
}

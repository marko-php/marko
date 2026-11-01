<?php

declare(strict_types=1);

namespace Marko\View\Twig\Tests\Fixtures;

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\View\Discovery\ViewProviderCacheContributor;
use Marko\View\Twig\TemplateFunctionExtension;
use Marko\View\ViewDataMerger;

/**
 * Builds the marko/view provider services for the given provider classes, as a cached boot would.
 */
class ViewProviders
{
    /**
     * @param list<class-string> $functionProviders
     * @param list<class-string> $dataProviders
     */
    public static function container(
        array $functionProviders = [],
        array $dataProviders = [],
    ): Container {
        $container = new Container();
        $container->instance(ContainerInterface::class, $container);
        $container->instance(ModuleRepositoryInterface::class, new ModuleRepository([]));
        $container->instance(CachedDiscovery::class, new CachedDiscovery([
            ViewProviderCacheContributor::KEY => [
                'template_functions' => $functionProviders,
                'view_data' => $dataProviders,
            ],
        ]));

        return $container;
    }

    /**
     * @param list<class-string> $functionProviders
     */
    public static function extension(
        array $functionProviders = [],
    ): TemplateFunctionExtension {
        return self::container($functionProviders)->get(TemplateFunctionExtension::class);
    }

    /**
     * @param list<class-string> $dataProviders
     */
    public static function merger(
        array $dataProviders = [],
    ): ViewDataMerger {
        return self::container(dataProviders: $dataProviders)->get(ViewDataMerger::class);
    }
}

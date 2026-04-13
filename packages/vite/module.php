<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Vite\AssetUrlGenerator;
use Marko\Vite\Contracts\AssetUrlGeneratorInterface;
use Marko\Vite\Contracts\DefaultEntrypointProviderInterface;
use Marko\Vite\Contracts\DevServerResolverInterface;
use Marko\Vite\Contracts\EntrypointResolverInterface;
use Marko\Vite\Contracts\ManifestRepositoryInterface;
use Marko\Vite\Contracts\TagRendererInterface;
use Marko\Vite\Contracts\VitePublisherInterface;
use Marko\Vite\Contracts\ViteManagerInterface;
use Marko\Vite\DefaultEntrypointProvider;
use Marko\Vite\DevServerResolver;
use Marko\Vite\EntrypointResolver;
use Marko\Vite\ManifestRepository;
use Marko\Vite\PackageJsonUpdater;
use Marko\Vite\ProjectFilePublisher;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\TagRenderer;
use Marko\Vite\ValueObjects\ViteConfig;
use Marko\Vite\ViteManager;
use Marko\Vite\VitePublisher;
use Marko\Vite\ViteViewHelper;

return [
    'bindings' => [
        ViteConfig::class => static function (ContainerInterface $container): ViteConfig {
            $config = $container->get(ConfigRepositoryInterface::class);
            $paths = $container->get(ProjectPaths::class);

            return ViteConfig::fromArray($config->getArray('vite'), $paths->base);
        },
        ManifestRepositoryInterface::class => ManifestRepository::class,
        DevServerResolverInterface::class => DevServerResolver::class,
        AssetUrlGeneratorInterface::class => AssetUrlGenerator::class,
        DefaultEntrypointProviderInterface::class => DefaultEntrypointProvider::class,
        EntrypointResolverInterface::class => EntrypointResolver::class,
        TagRendererInterface::class => TagRenderer::class,
        ViteManagerInterface::class => ViteManager::class,
        VitePublisherInterface::class => VitePublisher::class,
    ],
    'singletons' => [
        ViteConfig::class,
        ManifestRepository::class,
        DevServerResolver::class,
        AssetUrlGenerator::class,
        DefaultEntrypointProvider::class,
        EntrypointResolver::class,
        TagRenderer::class,
        ViteManager::class,
        VitePublisher::class,
        PackageJsonUpdater::class,
        ProjectFilePublisher::class,
        ScaffoldTemplateRenderer::class,
        ViteViewHelper::class,
    ],
];

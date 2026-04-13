<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Inertia\ControllerLayoutPageMetadataResolver;
use Marko\Inertia\Inertia;
use Marko\Inertia\InertiaConfig;
use Marko\Inertia\Interfaces\ComponentResolverInterface;
use Marko\Inertia\Interfaces\InertiaInterface;
use Marko\Inertia\Interfaces\RootRendererInterface;
use Marko\Inertia\Interfaces\SsrGatewayInterface;
use Marko\Inertia\Props\PropsResolver;
use Marko\Inertia\Rendering\ModuleComponentResolver;
use Marko\Inertia\Rendering\RootRenderer;
use Marko\Inertia\Response\ResponseFactory;
use Marko\Routing\RouteMatcherInterface;
use Marko\Inertia\Ssr\SsrGateway;
use Marko\Session\Contracts\SessionInterface;

return [
    'bindings' => [
        InertiaConfig::class => static function (ContainerInterface $container): InertiaConfig {
            return new InertiaConfig($container->get(ConfigRepositoryInterface::class));
        },
        ResponseFactory::class => static function (ContainerInterface $container): ResponseFactory {
            return new ResponseFactory(
                config: $container->get(InertiaConfig::class),
                components: $container->get(ComponentResolverInterface::class),
                rootRenderer: $container->get(RootRendererInterface::class),
                events: $container->get(EventDispatcherInterface::class),
                propsResolver: $container->get(PropsResolver::class),
                sessionResolver: static function () use ($container): ?object {
                    try {
                        return $container->get(SessionInterface::class);
                    } catch (Throwable) {
                        return null;
                    }
                },
                pageMetadataResolver: static function (
                    \Marko\Routing\Http\Request $request,
                    string $component,
                    array $props,
                ) use ($container): array {
                    try {
                        $resolver = $container->get(ControllerLayoutPageMetadataResolver::class);
                    } catch (Throwable) {
                        return [];
                    }

                    $controller = null;
                    $action = null;

                    try {
                        $matchedRoute = $container->get(RouteMatcherInterface::class)
                            ->match($request->method(), $request->path());

                        if ($matchedRoute !== null) {
                            $controller = $matchedRoute->route->controller;
                            $action = $matchedRoute->route->action;
                        }
                    } catch (Throwable) {
                        // Fall back to stack inspection when routing services are unavailable.
                    }

                    if ($controller === null || $action === null) {
                        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);

                        foreach ($trace as $frame) {
                            $class = $frame['class'] ?? null;
                            $function = $frame['function'] ?? null;

                            if (! is_string($class) || ! is_string($function)) {
                                continue;
                            }

                            if (str_starts_with($class, 'Marko\\Inertia\\')) {
                                continue;
                            }

                            $controller = $class;
                            $action = $function;

                            break;
                        }
                    }

                    return $resolver->resolve($controller, $action);
                },
            );
        },
        InertiaInterface::class => Inertia::class,
        ComponentResolverInterface::class => ModuleComponentResolver::class,
        RootRendererInterface::class => RootRenderer::class,
        SsrGatewayInterface::class => SsrGateway::class,
    ],
    'singletons' => [
        InertiaConfig::class,
        ModuleComponentResolver::class,
        ControllerLayoutPageMetadataResolver::class,
        PropsResolver::class,
        RootRenderer::class,
        SsrGateway::class,
    ],
];

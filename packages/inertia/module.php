<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Inertia\Inertia;
use Marko\Inertia\Ssr\CurlSsrTransport;
use Marko\Inertia\Ssr\SsrClient;
use Marko\Inertia\Ssr\SsrTransportInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Vite\Vite;

return [
    'enabled' => true,
    'sequence' => [
        'after' => ['marko/config', 'marko/routing', 'marko/session', 'marko/vite'],
    ],
    'bindings' => [
        SsrTransportInterface::class => CurlSsrTransport::class,
        SsrClient::class => function (ContainerInterface $container): SsrClient {
            $config = $container->get(ConfigRepositoryInterface::class);
            $transport = $container->get(SsrTransportInterface::class);

            if (! $config instanceof ConfigRepositoryInterface) {
                throw new RuntimeException('Config repository binding must implement '.ConfigRepositoryInterface::class);
            }

            if (! $transport instanceof SsrTransportInterface) {
                throw new RuntimeException('SSR transport binding must implement '.SsrTransportInterface::class);
            }

            return new SsrClient(
                $config->getString('inertia.ssr.url'),
                $transport,
            );
        },
        Inertia::class => function (ContainerInterface $container): Inertia {
            $config = $container->get(ConfigRepositoryInterface::class);
            $vite = $container->get(Vite::class);
            $ssrClient = $container->get(SsrClient::class);
            $session = $container->get(SessionInterface::class);

            if (! $config instanceof ConfigRepositoryInterface) {
                throw new RuntimeException('Config repository binding must implement '.ConfigRepositoryInterface::class);
            }

            if (! $vite instanceof Vite) {
                throw new RuntimeException('Vite binding must resolve to '.Vite::class);
            }

            if (! $ssrClient instanceof SsrClient) {
                throw new RuntimeException('SSR client binding must resolve to '.SsrClient::class);
            }

            if (! $session instanceof SessionInterface) {
                throw new RuntimeException('Session binding must implement '.SessionInterface::class);
            }

            return new Inertia(
                $config,
                $vite,
                $ssrClient,
                $session,
            );
        },
    ],
    'singletons' => [
        Inertia::class,
        SsrClient::class,
    ],
];

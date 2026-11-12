<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Config\Exceptions\ConfigException;
use Marko\Core\Container\ContainerInterface;
use Marko\Webhook\Config\WebhookConfig;
use Marko\Webhook\Contracts\HostResolverInterface;
use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Contracts\WebhookReceiverInterface;
use Marko\Webhook\Contracts\WebhookReplayGuardInterface;
use Marko\Webhook\Contracts\WebhookUrlPolicyInterface;
use Marko\Webhook\Middleware\WebhookEndpointMiddleware;
use Marko\Webhook\Receiving\CacheReplayGuard;
use Marko\Webhook\Receiving\WebhookReceiver;
use Marko\Webhook\Sending\DnsHostResolver;
use Marko\Webhook\Sending\WebhookDispatcher;
use Marko\Webhook\Sending\WebhookUrlPolicy;

return [
    'bindings' => [
        WebhookDispatcherInterface::class => WebhookDispatcher::class,
        WebhookUrlPolicyInterface::class => WebhookUrlPolicy::class,
        HostResolverInterface::class => DnsHostResolver::class,
        WebhookReceiverInterface::class => WebhookReceiver::class,
        WebhookReplayGuardInterface::class => function (ContainerInterface $container): WebhookReplayGuardInterface {
            if (!interface_exists(CacheInterface::class)) {
                throw new ConfigException(
                    message: 'Configuration key "webhook.replay_protection" is true, but marko/cache is not installed',
                    context: 'While resolving the replay guard that remembers received X-Webhook-Id values.',
                    suggestion: 'Run composer require marko/cache plus a shared cache driver (e.g. marko/cache-redis), bind your own WebhookReplayGuardInterface, or set webhook.replay_protection to false.',
                );
            }

            return new CacheReplayGuard($container->get(CacheInterface::class));
        },
    ],
    'arguments' => [
        WebhookReceiver::class => [
            // The replay guard is only resolved when webhook.replay_protection is on, so marko/cache stays optional.
            'replayGuard' => fn (ContainerInterface $container): ?WebhookReplayGuardInterface => $container->get(
                WebhookConfig::class,
            )->replayProtection
                ? $container->get(WebhookReplayGuardInterface::class)
                : null,
        ],
    ],
    'globalMiddleware' => [
        WebhookEndpointMiddleware::class,
    ],
];

<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Query\ScopedOrderByFactory;
use Marko\Scope\Registry\PhpScopeRegistry;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Resolution\ScopeWalker;
use Marko\Scope\Resolver\ScopeResolver;

return [
    'bindings' => [
        ScopeRegistryInterface::class => function (ContainerInterface $container): PhpScopeRegistry {
            return new PhpScopeRegistry($container->get(ConfigRepositoryInterface::class));
        },
    ],
    'singletons' => [
        ScopeContext::class,
        ScopeMetadataFactory::class,
        ScopeResolver::class,
        ScopedOrderByFactory::class,
        ScopeWalker::class,
    ],
];

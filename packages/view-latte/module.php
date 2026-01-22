<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewInterface;

return [
    'enabled' => true,
    'bindings' => [
        ViewInterface::class => function (ContainerInterface $container): ViewInterface {
            $engine = $container->get(LatteEngineFactory::class)->create();
            $resolver = $container->get(TemplateResolverInterface::class);

            return new LatteView($engine, $resolver);
        },
    ],
];

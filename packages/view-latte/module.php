<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Core\Container\ContainerInterface;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\ViewInterface;

return [
    'bindings' => [
        ViewInterface::class => LatteView::class,
        Engine::class => function (ContainerInterface $container): Engine {
            return $container->get(LatteEngineFactory::class)->create();
        },
    ],
];

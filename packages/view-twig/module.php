<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\View\Twig\TwigEngineFactory;
use Marko\View\Twig\TwigView;
use Marko\View\ViewInterface;
use Twig\Environment;

return [
    'bindings' => [
        ViewInterface::class => TwigView::class,
        Environment::class => function (ContainerInterface $container): Environment {
            return $container->get(TwigEngineFactory::class)->create();
        },
    ],
];

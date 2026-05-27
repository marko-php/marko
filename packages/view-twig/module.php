<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\View\TemplateResolverInterface;
use Marko\View\Twig\TwigEngineFactory;
use Marko\View\Twig\TwigView;
use Marko\View\ViewInterface;

return [
    'bindings' => [
        ViewInterface::class => function (ContainerInterface $container): ViewInterface {
            $engine = $container->get(TwigEngineFactory::class)->create();
            $resolver = $container->get(TemplateResolverInterface::class);

            return new TwigView($engine, $resolver);
        },
    ],
];

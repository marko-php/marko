<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Translation\Config\TranslationConfig;
use Marko\Translation\Contracts\TranslationLoaderInterface;
use Marko\Translation\Contracts\TranslatorInterface;
use Marko\Translation\Translator;

return [
    'bindings' => [
        TranslatorInterface::class => function (ContainerInterface $container): TranslatorInterface {
            $config = $container->get(TranslationConfig::class);
            $loader = $container->get(TranslationLoaderInterface::class);

            return new Translator(
                loader: $loader,
                locale: $config->locale,
                fallbackLocale: $config->fallbackLocale,
            );
        },
    ],
];

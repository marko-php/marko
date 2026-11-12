<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Translation\Config\TranslationConfig;
use Marko\Translation\Contracts\TranslatorInterface;
use Marko\Translation\Translator;

return [
    'bindings' => [
        TranslatorInterface::class => Translator::class,
    ],
    'arguments' => [
        Translator::class => [
            'locale' => fn (ContainerInterface $container): string => $container->get(
                TranslationConfig::class,
            )->locale,
            'fallbackLocale' => fn (ContainerInterface $container): string => $container->get(
                TranslationConfig::class,
            )->fallbackLocale,
        ],
    ],
];

<?php

declare(strict_types=1);

use Marko\Routing\RouteCacheContributor;
use Marko\Routing\Signing\HmacUrlSigner;
use Marko\Routing\Signing\UrlSignerInterface;
use Marko\Routing\UrlGenerator;
use Marko\Routing\UrlGeneratorInterface;

return [
    'singletons' => [
        UrlGeneratorInterface::class => UrlGenerator::class,
        UrlSignerInterface::class => HmacUrlSigner::class,
    ],
    'discovery' => [
        RouteCacheContributor::class,
    ],
];

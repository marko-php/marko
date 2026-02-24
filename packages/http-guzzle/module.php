<?php

declare(strict_types=1);

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Guzzle\GuzzleHttpClient;

return [
    'bindings' => [
        HttpClientInterface::class => GuzzleHttpClient::class,
    ],
];

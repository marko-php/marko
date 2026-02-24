<?php

declare(strict_types=1);

use Marko\Search\Contracts\SearchInterface;
use Marko\Search\Driver\DatabaseSearchDriver;

return [
    'bindings' => [
        SearchInterface::class => DatabaseSearchDriver::class,
    ],
];

<?php

declare(strict_types=1);

use Marko\Mail\Config\MailConfig;

return [
    'enabled' => true,
    'bindings' => [
        MailConfig::class => MailConfig::class,
    ],
];

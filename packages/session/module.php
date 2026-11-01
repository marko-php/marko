<?php

declare(strict_types=1);

use Marko\Session\Contracts\FlashDataInterface;
use Marko\Session\Flash\SessionFlashData;

// SessionInterface itself is bound by the session driver packages
// (marko/session-file, marko/session-database).
return [
    'bindings' => [
        FlashDataInterface::class => SessionFlashData::class,
    ],
];

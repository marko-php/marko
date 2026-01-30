<?php

declare(strict_types=1);

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Log\LogMailer;

return [
    'bindings' => [
        MailerInterface::class => LogMailer::class,
    ],
];

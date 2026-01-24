<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Smtp\SmtpMailerFactory;

return [
    'enabled' => true,
    'bindings' => [
        MailerInterface::class => function (ContainerInterface $container): MailerInterface {
            return $container->get(SmtpMailerFactory::class)->create();
        },
    ],
];

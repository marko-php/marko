<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Smtp\SmtpMailerFactory;
use Marko\Mail\Smtp\SocketInterface;
use Marko\Mail\Smtp\StreamSocket;

return [
    'bindings' => [
        SocketInterface::class => StreamSocket::class,
        MailerInterface::class => function (ContainerInterface $container): MailerInterface {
            return $container->get(SmtpMailerFactory::class)->create();
        },
    ],
];

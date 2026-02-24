<?php

declare(strict_types=1);

use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\OpenSsl\OpenSslEncryptor;

return [
    'bindings' => [
        EncryptorInterface::class => OpenSslEncryptor::class,
    ],
];

<?php

declare(strict_types=1);

use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\OpenSsl\OpenSslEncryptor;

// Mirrors packages/encryption-openssl/module.php — binds the concrete
// EncryptorInterface implementation that CsrfTokenManager needs.
return [
    'bindings' => [
        EncryptorInterface::class => OpenSslEncryptor::class,
    ],
];

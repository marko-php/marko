<?php

declare(strict_types=1);

return [
    'default' => $_ENV['HASH_DRIVER'] ?? 'bcrypt',

    'hashers' => [
        'bcrypt' => [
            'cost' => (int) ($_ENV['BCRYPT_COST'] ?? 12),
        ],

        'argon2id' => [
            'memory' => (int) ($_ENV['ARGON2_MEMORY'] ?? 65536),
            'time' => (int) ($_ENV['ARGON2_TIME'] ?? 4),
            'threads' => (int) ($_ENV['ARGON2_THREADS'] ?? 1),
        ],
    ],
];

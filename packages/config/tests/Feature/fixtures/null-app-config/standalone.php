<?php

declare(strict_types=1);

use Marko\Config\RemoveKey;

return [
    'secret' => null,
    'legacy' => new RemoveKey(),
    'nested' => [
        'keep' => true,
        'legacy' => new RemoveKey(),
    ],
];

<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['PUBSUB_DRIVER'] ?? 'redis',
    'prefix' => $_ENV['PUBSUB_PREFIX'] ?? 'marko:',
];

<?php

declare(strict_types=1);

return [
    'shutdown_timeout' => (int) ($_ENV['AMPHP_SHUTDOWN_TIMEOUT'] ?? 30),
    'channels' => array_filter(explode(',', (string) ($_ENV['AMPHP_CHANNELS'] ?? ''))),
];

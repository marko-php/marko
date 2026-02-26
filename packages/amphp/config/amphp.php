<?php

declare(strict_types=1);

return [
    'shutdown_timeout' => (int) ($_ENV['AMPHP_SHUTDOWN_TIMEOUT'] ?? 30),
];

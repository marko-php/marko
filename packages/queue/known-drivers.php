<?php

declare(strict_types=1);

return [
    'marko/queue-sync' => 'Synchronous queue driver (recommended; runs jobs inline, no infrastructure)',
    'marko/queue-database' => 'Database-backed queue driver (production-ready; uses your existing database)',
    'marko/queue-rabbitmq' => 'RabbitMQ queue driver (high-throughput production deployments)',
];

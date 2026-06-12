<?php

declare(strict_types=1);

use Marko\Queue\JobEnvelope;

return [
    'bindings' => [
        JobEnvelope::class => JobEnvelope::class,
    ],
];

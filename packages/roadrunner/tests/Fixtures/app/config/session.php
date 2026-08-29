<?php

declare(strict_types=1);

// A file-backed session store scoped to this PHP process. Deterministic
// (PID-based, not random) so the value is identical no matter how many
// times ConfigRepositoryInterface is re-resolved during a single boot.
return [
    'driver' => 'file',
    'lifetime' => 120,
    'expire_on_close' => false,
    'path' => sys_get_temp_dir() . '/marko-roadrunner-harness/' . getmypid() . '/sessions',

    'cookie' => [
        'name' => 'marko_session',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'lax',
    ],

    'gc_probability' => 0,
    'gc_divisor' => 100,
];

<?php

declare(strict_types=1);

// A fixed, insecure key: fine for a fixture that only ever encrypts a CSRF
// token inside this test process, never for a real application.
return [
    'key' => base64_encode(str_repeat('a', 32)),
    'cipher' => 'aes-256-gcm',
];

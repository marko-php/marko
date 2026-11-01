<?php

declare(strict_types=1);

return [
    // Input fields that are never flashed back into the form after a failed
    // submission. Dot paths reach nested fields (for example 'user.password').
    // The CSRF token and uploaded files are never flashed either.
    'never_flash' => [
        'password',
        'password_confirmation',
        'current_password',
    ],

    // Where ValidationErrorResponder sends the user when the request has
    // no Referer, or one from another origin. Must be a path on this site.
    'fallback_redirect' => '/',
];

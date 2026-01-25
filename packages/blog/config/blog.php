<?php

declare(strict_types=1);

return [
    // Pagination
    'posts_per_page' => 10,

    // Comments
    'comment_max_depth' => 5,
    'comment_rate_limit_seconds' => 30,

    // Email Verification
    'verification_token_expiry_days' => 7,
    'verification_cookie_days' => 365,
    'verification_cookie_name' => 'blog_verified',

    // Routing
    'route_prefix' => '/blog',
];

<?php

declare(strict_types=1);

return [
    'locale' => $_ENV['APP_LOCALE'] ?? 'en',
    'fallback_locale' => $_ENV['APP_FALLBACK_LOCALE'] ?? 'en',
];

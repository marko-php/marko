# Task 005: SecurityConfig, module.php, composer.json, Config File

**Status**: done
**Depends on**: 001, 002, 003, 004
**Retry count**: 0

## Description
Create SecurityConfig (wraps ConfigRepositoryInterface), the config/security.php defaults file, module.php with bindings, composer.json, and Pest.php. Wire everything together so the package is installable and functional.

## Context
- SecurityConfig is a readonly class that reads from ConfigRepositoryInterface
- Config sections: `security.csrf.*`, `security.cors.*`, `security.headers.*`
- config/security.php provides sensible defaults for all settings
- module.php binds CsrfTokenManagerInterface to CsrfTokenManager
- composer.json declares dependencies: marko/core, marko/config, marko/routing, marko/session, marko/encryption
- Also create tests/Pest.php for test configuration

### Config Structure (config/security.php)
```php
return [
    'csrf' => [
        'session_key' => '_csrf_token',
    ],
    'cors' => [
        'allowed_origins' => [],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-CSRF-TOKEN'],
        'max_age' => 86400,
    ],
    'headers' => [
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'SAMEORIGIN',
        'x_xss_protection' => '1; mode=block',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => "default-src 'self'",
    ],
];
```

## Requirements (Test Descriptions)
- [ ] `it creates SecurityConfig with CORS settings from config repository`
- [ ] `it creates SecurityConfig with headers settings from config repository`
- [ ] `it creates SecurityConfig with CSRF session key from config repository`
- [ ] `it has valid composer.json with marko module flag and correct dependencies`
- [ ] `it binds CsrfTokenManagerInterface to CsrfTokenManager in module.php`
- [ ] `it provides sensible defaults in config/security.php`

## Acceptance Criteria
- SecurityConfig is readonly class accepting ConfigRepositoryInterface
- SecurityConfig exposes methods: corsAllowedOrigins(), corsAllowedMethods(), corsAllowedHeaders(), corsMaxAge(), csrfSessionKey(), headerXContentTypeOptions(), headerXFrameOptions(), headerXXssProtection(), headerStrictTransportSecurity(), headerReferrerPolicy(), headerContentSecurityPolicy()
- config/security.php returns array with all default values
- module.php binds CsrfTokenManagerInterface => CsrfTokenManager
- composer.json has type "marko-module", correct dependencies, PSR-4 autoload for Marko\Security\
- tests/Pest.php exists with strict_types
- All files have strict_types=1

## Implementation Notes


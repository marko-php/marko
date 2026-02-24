<?php

declare(strict_types=1);

use Marko\Security\Config\SecurityConfig;
use Marko\Testing\Fake\FakeConfigRepository;

describe('SecurityConfig', function (): void {
    it('creates SecurityConfig with CORS settings from config repository', function (): void {
        $config = new SecurityConfig(new FakeConfigRepository([
            'security.cors.allowed_origins' => ['https://example.com'],
            'security.cors.allowed_methods' => ['GET', 'POST'],
            'security.cors.allowed_headers' => ['Content-Type'],
            'security.cors.max_age' => 3600,
        ]));

        expect($config->corsAllowedOrigins())->toBe(['https://example.com'])
            ->and($config->corsAllowedMethods())->toBe(['GET', 'POST'])
            ->and($config->corsAllowedHeaders())->toBe(['Content-Type'])
            ->and($config->corsMaxAge())->toBe(3600);
    });

    it('creates SecurityConfig with headers settings from config repository', function (): void {
        $config = new SecurityConfig(new FakeConfigRepository([
            'security.headers.x_content_type_options' => 'nosniff',
            'security.headers.x_frame_options' => 'DENY',
            'security.headers.x_xss_protection' => '1; mode=block',
            'security.headers.strict_transport_security' => 'max-age=31536000',
            'security.headers.referrer_policy' => 'no-referrer',
            'security.headers.content_security_policy' => "default-src 'self'",
        ]));

        expect($config->headerXContentTypeOptions())->toBe('nosniff')
            ->and($config->headerXFrameOptions())->toBe('DENY')
            ->and($config->headerXXssProtection())->toBe('1; mode=block')
            ->and($config->headerStrictTransportSecurity())->toBe('max-age=31536000')
            ->and($config->headerReferrerPolicy())->toBe('no-referrer')
            ->and($config->headerContentSecurityPolicy())->toBe("default-src 'self'");
    });

    it('creates SecurityConfig with CSRF session key from config repository', function (): void {
        $config = new SecurityConfig(new FakeConfigRepository([
            'security.csrf.session_key' => '_csrf_token',
        ]));

        expect($config->csrfSessionKey())->toBe('_csrf_token');
    });

    it('uses FakeConfigRepository instead of inline config stub in SecurityConfigTest', function (): void {
        $repo = new FakeConfigRepository([
            'security.cors.allowed_origins' => ['*'],
        ]);
        $config = new SecurityConfig($repo);

        expect($repo)->toBeInstanceOf(FakeConfigRepository::class)
            ->and($config->corsAllowedOrigins())->toBe(['*']);
    });
});

describe('composer.json', function (): void {
    it('has valid composer.json with marko module flag and correct dependencies', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect(file_exists($composerPath))->toBeTrue()
            ->and($composer['name'])->toBe('marko/security')
            ->and($composer['type'])->toBe('marko-module')
            ->and($composer['require'])->toHaveKey('php')
            ->and($composer['require'])->toHaveKey('marko/core')
            ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Security\\')
            ->and($composer['extra']['marko']['module'])->toBeTrue();
    });
});

describe('module.php', function (): void {
    it('binds CsrfTokenManagerInterface to CsrfTokenManager in module.php', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $module = require $modulePath;

        expect(file_exists($modulePath))->toBeTrue()
            ->and($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toHaveKey('Marko\Security\Contracts\CsrfTokenManagerInterface');
    });
});

describe('config/security.php', function (): void {
    it('provides sensible defaults in config/security.php', function (): void {
        $configPath = dirname(__DIR__, 2) . '/config/security.php';
        $config = require $configPath;

        expect(file_exists($configPath))->toBeTrue()
            ->and($config)->toBeArray()
            ->and($config)->toHaveKey('csrf')
            ->and($config['csrf'])->toHaveKey('session_key')
            ->and($config['csrf']['session_key'])->toBe('_csrf_token')
            ->and($config)->toHaveKey('cors')
            ->and($config['cors'])->toHaveKey('allowed_origins')
            ->and($config['cors'])->toHaveKey('allowed_methods')
            ->and($config['cors'])->toHaveKey('allowed_headers')
            ->and($config['cors'])->toHaveKey('max_age')
            ->and($config['cors']['max_age'])->toBe(86400)
            ->and($config)->toHaveKey('headers')
            ->and($config['headers'])->toHaveKey('x_content_type_options')
            ->and($config['headers']['x_content_type_options'])->toBe('nosniff')
            ->and($config['headers'])->toHaveKey('x_frame_options')
            ->and($config['headers']['x_frame_options'])->toBe('SAMEORIGIN')
            ->and($config['headers'])->toHaveKey('x_xss_protection')
            ->and($config['headers'])->toHaveKey('strict_transport_security')
            ->and($config['headers'])->toHaveKey('referrer_policy')
            ->and($config['headers'])->toHaveKey('content_security_policy');
    });
});

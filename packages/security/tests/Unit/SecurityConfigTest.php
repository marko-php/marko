<?php

declare(strict_types=1);

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\ManifestParser;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Security\Config\SecurityConfig;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\CsrfTokenManager;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\Security\Tests\Helpers;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;

describe('SecurityConfig', function (): void {
    it('has no CORS getters because marko/cors owns CORS', function (): void {
        $methods = array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            new ReflectionClass(SecurityConfig::class)->getMethods(),
        );

        expect(array_filter($methods, fn (string $name): bool => str_starts_with($name, 'cors')))->toBeEmpty();
    });

    it('does not ship a CORS middleware in marko/security', function (): void {
        expect(file_exists(dirname(__DIR__, 2) . '/src/Middleware/CorsMiddleware.php'))->toBeFalse();
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
            'security.csrf.session_key' => '_token',
        ]);
        $config = new SecurityConfig($repo);

        expect($repo)->toBeInstanceOf(FakeConfigRepository::class)
            ->and($config->csrfSessionKey())->toBe('_token');
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
            ->and($module['bindings'])->toHaveKey(CsrfTokenManagerInterface::class);
    });

    it('registers the security middleware globally after the session drivers', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';

        // SecurityHeadersMiddleware is listed first so it wraps CsrfMiddleware
        // and also decorates the 419 a token mismatch renders.
        expect($module['globalMiddleware'])->toBe([SecurityHeadersMiddleware::class, CsrfMiddleware::class])
            ->and($module['sequence']['after'])->toContain('marko/session-file', 'marko/session-database');
    });

    it('binds CsrfTokenManagerInterface to CsrfTokenManager as a class binding', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';

        expect($module['bindings'][CsrfTokenManagerInterface::class])->toBe(CsrfTokenManager::class);
    });

    it('builds CsrfTokenManager with the configured security.csrf.session_key', function (): void {
        $session = new FakeSession();
        $container = new Container(new PreferenceRegistry());
        new BindingRegistry($container)->registerModule(new ManifestParser()->parse(dirname(__DIR__, 2)));
        $container->instance(SessionInterface::class, $session);
        $container->instance(EncryptorInterface::class, $this->createStub(EncryptorInterface::class));
        $container->instance(SecurityConfig::class, Helpers::createSecurityConfig([
            'security.csrf.session_key' => '_custom_csrf',
        ]));

        $token = $container->get(CsrfTokenManagerInterface::class)->get();

        expect($session->get('_custom_csrf'))->toBe($token)
            ->and($session->has('_csrf_token'))->toBeFalse();
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
            ->and($config)->not->toHaveKey('cors')
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

    it('disables the legacy browser XSS auditor by default', function (): void {
        $config = require dirname(__DIR__, 2) . '/config/security.php';

        // "1; mode=block" is deprecated and enabled XS-Leak attacks in the
        // browsers that still honoured it; OWASP and MDN recommend "0".
        expect($config['headers']['x_xss_protection'])->toBe('0');
    });
});

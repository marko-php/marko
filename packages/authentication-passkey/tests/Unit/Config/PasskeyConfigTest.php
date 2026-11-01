<?php

declare(strict_types=1);

use Marko\AuthenticationPasskey\Config\PasskeyConfig;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\AuthenticationPasskey\Tests\Fixtures\PasskeyHarness;
use Marko\Testing\Fake\FakeConfigRepository;

function passkeyConfig(
    array $overrides = [],
): PasskeyConfig {
    return new PasskeyConfig(new FakeConfigRepository([...PasskeyHarness::PASSKEY_CONFIG, ...$overrides]));
}

it('reads a valid configuration', function (): void {
    $config = passkeyConfig([
        'authentication-passkey.relying_party.id' => ' Example.COM ',
        'authentication-passkey.allowed_origins' => ['https://example.com', 'HTTPS://App.Example.com:443', 'https://example.com:8443'],
    ]);

    expect($config->relyingPartyId())->toBe('example.com')
        ->and($config->relyingPartyName())->toBe('Example')
        ->and($config->allowedOrigins())->toBe(
            ['https://example.com', 'https://app.example.com', 'https://example.com:8443'],
        )
        ->and($config->guard())->toBe('session')
        ->and($config->timeoutSeconds())->toBe(300)
        ->and($config->throttleMaxAttempts())->toBe(5)
        ->and($config->throttleDecaySeconds())->toBe(60);
});

it('accepts plain http for localhost only', function (): void {
    $config = passkeyConfig([
        'authentication-passkey.relying_party.id' => 'localhost',
        'authentication-passkey.allowed_origins' => ['http://localhost:8000', 'http://app.localhost'],
    ]);

    expect($config->allowedOrigins())->toBe(['http://localhost:8000', 'http://app.localhost']);
});

it('throws on misconfiguration', function (array $overrides, string $method, string $message): void {
    expect(fn () => passkeyConfig($overrides)->$method())->toThrow(PasskeyConfigurationException::class, $message);
})->with([
    'missing rp id' => [['authentication-passkey.relying_party.id' => null], 'relyingPartyId', 'relying_party.id" must be set'],
    'empty rp id' => [['authentication-passkey.relying_party.id' => ' '], 'relyingPartyId', 'relying_party.id" must be set'],
    'rp id with scheme' => [['authentication-passkey.relying_party.id' => 'https://example.com'], 'relyingPartyId', 'must be a domain name'],
    'rp id with port' => [['authentication-passkey.relying_party.id' => 'example.com:443'], 'relyingPartyId', 'must be a domain name'],
    'rp id ip address' => [['authentication-passkey.relying_party.id' => '127.0.0.1'], 'relyingPartyId', 'must be a domain name'],
    'missing rp name' => [['authentication-passkey.relying_party.name' => null], 'relyingPartyName', 'relying_party.name" must be set'],
    'no origins' => [['authentication-passkey.allowed_origins' => []], 'allowedOrigins', 'must be a non-empty list'],
    'http origin outside localhost' => [['authentication-passkey.allowed_origins' => ['http://example.com']], 'allowedOrigins', 'must only contain https origins'],
    'origin on another domain' => [['authentication-passkey.allowed_origins' => ['https://evilexample.com']], 'allowedOrigins', "relying party id 'example.com'"],
    'origin with path' => [['authentication-passkey.allowed_origins' => ['https://example.com/login']], 'allowedOrigins', 'scheme://host[:port]'],
    'origin with trailing slash' => [['authentication-passkey.allowed_origins' => ['https://example.com/']], 'allowedOrigins', 'scheme://host[:port]'],
    'origin with query' => [['authentication-passkey.allowed_origins' => ['https://example.com?x=1']], 'allowedOrigins', 'scheme://host[:port]'],
    'origin not a string' => [['authentication-passkey.allowed_origins' => [42]], 'allowedOrigins', 'scheme://host[:port]'],
    'missing guard' => [['authentication-passkey.guard' => ''], 'guard', 'must name a guard'],
    'zero timeout' => [['authentication-passkey.timeout_seconds' => 0], 'timeoutSeconds', 'must be a positive integer'],
    'string max attempts' => [['authentication-passkey.throttle.max_attempts' => '5'], 'throttleMaxAttempts', 'must be a positive integer'],
    'missing decay' => [['authentication-passkey.throttle.decay_seconds' => null], 'throttleDecaySeconds', 'must be a positive integer'],
]);

it('ships a config file whose required values come from the environment', function (): void {
    $config = require dirname(__DIR__, 3) . '/config/authentication-passkey.php';

    expect(array_keys($config))->toBe(['relying_party', 'allowed_origins', 'guard', 'timeout_seconds', 'throttle'])
        ->and($config['guard'])->toBe('session')
        ->and($config['timeout_seconds'])->toBe(300)
        ->and($config['throttle'])->toBe(['max_attempts' => 5, 'decay_seconds' => 60]);
});

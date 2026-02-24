<?php

declare(strict_types=1);

use Marko\Mail\Config\MailConfig;
use Marko\Mail\Exception\MailException;
use Marko\Testing\Fake\FakeConfigRepository;

test('MailConfig loads driver setting', function (): void {
    $config = new MailConfig(new FakeConfigRepository([
        'mail.driver' => 'smtp',
    ]));

    expect($config->driver())->toBe('smtp');
});

test('MailConfig loads from address', function (): void {
    $config = new MailConfig(new FakeConfigRepository([
        'mail.from.address' => 'hello@example.com',
    ]));

    expect($config->fromAddress())->toBe('hello@example.com');
});

test('MailConfig loads from name', function (): void {
    $config = new MailConfig(new FakeConfigRepository([
        'mail.from.name' => 'Marko Application',
    ]));

    expect($config->fromName())->toBe('Marko Application');
});

test('MailConfig provides driver-specific config', function (): void {
    $smtpConfig = [
        'host' => 'localhost',
        'port' => 587,
        'encryption' => 'tls',
    ];
    $config = new MailConfig(new FakeConfigRepository([
        'mail.smtp' => $smtpConfig,
    ]));

    expect($config->driverConfig('smtp'))->toBe($smtpConfig);
});

test('MailConfig throws on missing config file', function (): void {
    $config = new MailConfig(new FakeConfigRepository());

    expect(fn () => $config->ensureConfigExists())
        ->toThrow(MailException::class, 'Mail configuration file not found.');
});

test('provides default configuration file', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/mail.php';

    expect(file_exists($configPath))->toBeTrue()
        ->and(is_array(require $configPath))->toBeTrue();
});

test('it reads driver from config without fallback', function (): void {
    $config = new MailConfig(new FakeConfigRepository([
        'mail.driver' => 'sendmail',
    ]));

    expect($config->driver())->toBe('sendmail');
});

test('it reads from address from config without fallback', function (): void {
    $config = new MailConfig(new FakeConfigRepository([
        'mail.from.address' => 'test@example.org',
    ]));

    expect($config->fromAddress())->toBe('test@example.org');
});

test('it reads from name from config without fallback', function (): void {
    $config = new MailConfig(new FakeConfigRepository([
        'mail.from.name' => 'Test Sender',
    ]));

    expect($config->fromName())->toBe('Test Sender');
});

test('config file contains all required keys with defaults', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/mail.php';
    $config = require $configPath;

    expect($config)->toBeArray()
        ->toHaveKey('driver')
        ->toHaveKey('from')
        ->and($config['from'])->toBeArray()
        ->toHaveKey('address')
        ->toHaveKey('name')
        ->and($config['driver'])->toBe('smtp')
        ->and($config['from']['address'])->toBe('hello@example.com')
        ->and($config['from']['name'])->toBe('Marko Application');
});

test('it uses FakeConfigRepository in MailConfigTest', function (): void {
    $repo = new FakeConfigRepository(['mail.driver' => 'smtp']);
    $config = new MailConfig($repo);

    expect($config->driver())->toBe('smtp');
});

<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Hashing\Config\HashConfig;
use Marko\Testing\Fake\FakeConfigRepository;

it('uses FakeConfigRepository in HashConfigTest', function (): void {
    $config = new FakeConfigRepository(['hashing.default' => 'bcrypt']);

    expect($config)->toBeInstanceOf(FakeConfigRepository::class);
});

it('reads default hasher from config without fallback', function (): void {
    $config = new HashConfig(new FakeConfigRepository([
        'hashing.default' => 'argon2id',
    ]));

    expect($config->defaultHasher())->toBe('argon2id');
});

it('throws exception when default hasher not configured', function (): void {
    $config = new HashConfig(new FakeConfigRepository());

    $config->defaultHasher();
})->throws(ConfigNotFoundException::class);

it('reads bcrypt cost from config without fallback', function (): void {
    $config = new HashConfig(new FakeConfigRepository([
        'hashing.hashers.bcrypt.cost' => 14,
    ]));

    expect($config->getBcryptCost())->toBe(14);
});

it('throws exception when bcrypt cost not configured', function (): void {
    $config = new HashConfig(new FakeConfigRepository());

    $config->getBcryptCost();
})->throws(ConfigNotFoundException::class);

it('reads argon2id memory from config without fallback', function (): void {
    $config = new HashConfig(new FakeConfigRepository([
        'hashing.hashers.argon2id.memory' => 131072,
    ]));

    expect($config->getArgon2Memory())->toBe(131072);
});

it('throws exception when argon2id memory not configured', function (): void {
    $config = new HashConfig(new FakeConfigRepository());

    $config->getArgon2Memory();
})->throws(ConfigNotFoundException::class);

it('reads argon2id time from config without fallback', function (): void {
    $config = new HashConfig(new FakeConfigRepository([
        'hashing.hashers.argon2id.time' => 8,
    ]));

    expect($config->getArgon2Time())->toBe(8);
});

it('throws exception when argon2id time not configured', function (): void {
    $config = new HashConfig(new FakeConfigRepository());

    $config->getArgon2Time();
})->throws(ConfigNotFoundException::class);

it('reads argon2id threads from config without fallback', function (): void {
    $config = new HashConfig(new FakeConfigRepository([
        'hashing.hashers.argon2id.threads' => 4,
    ]));

    expect($config->getArgon2Threads())->toBe(4);
});

it('throws exception when argon2id threads not configured', function (): void {
    $config = new HashConfig(new FakeConfigRepository());

    $config->getArgon2Threads();
})->throws(ConfigNotFoundException::class);

it('returns true when hasher is configured', function (): void {
    $config = new HashConfig(new FakeConfigRepository([
        'hashing.hashers.bcrypt' => ['cost' => 12],
    ]));

    expect($config->hasHasher('bcrypt'))->toBeTrue();
});

it('returns false when hasher is not configured', function (): void {
    $config = new HashConfig(new FakeConfigRepository());

    expect($config->hasHasher('unknown'))->toBeFalse();
});

it('returns hasher config array', function (): void {
    $config = new HashConfig(new FakeConfigRepository([
        'hashing.hashers.bcrypt' => ['cost' => 14],
    ]));

    expect($config->getHasherConfig('bcrypt'))->toBe(['cost' => 14]);
});

it('throws exception when hasher config not found', function (): void {
    $config = new HashConfig(new FakeConfigRepository());

    $config->getHasherConfig('unknown');
})->throws(ConfigNotFoundException::class);

describe('config file', function (): void {
    it('contains all required keys with defaults', function (): void {
        $configPath = dirname(__DIR__, 3) . '/config/hashing.php';
        $config = require $configPath;

        expect($config)->toHaveKey('default')
            ->and($config)->toHaveKey('hashers')
            ->and($config['hashers'])->toHaveKey('bcrypt')
            ->and($config['hashers']['bcrypt'])->toHaveKey('cost')
            ->and($config['hashers'])->toHaveKey('argon2id')
            ->and($config['hashers']['argon2id'])->toHaveKey('memory')
            ->and($config['hashers']['argon2id'])->toHaveKey('time')
            ->and($config['hashers']['argon2id'])->toHaveKey('threads');
    });
});

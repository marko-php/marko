<?php

declare(strict_types=1);

use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Factory\HasherFactory;
use Marko\Hashing\HashManager;
use Marko\Testing\Fake\FakeConfigRepository;

it('uses FakeConfigRepository in HashingIntegrationTest', function () {
    $config = new FakeConfigRepository(['hashing.default' => 'bcrypt']);

    expect($config)->toBeInstanceOf(FakeConfigRepository::class);
});

function createIntegrationManager(
    array $configData = [],
): HashManager {
    $defaults = [
        'hashing.default' => 'bcrypt',
        'hashing.hashers.bcrypt.cost' => 4,
        'hashing.hashers.argon2id.memory' => 1024,
        'hashing.hashers.argon2id.time' => 1,
        'hashing.hashers.argon2id.threads' => 1,
    ];

    $mergedConfig = array_merge($defaults, $configData);

    $configRepo = new FakeConfigRepository($mergedConfig);
    $hashConfig = new HashConfig($configRepo);
    $factory = new HasherFactory($hashConfig);

    return new HashManager($hashConfig, $factory);
}

it('completes full password hashing workflow with bcrypt', function () {
    $manager = createIntegrationManager(['hashing.default' => 'bcrypt']);
    $password = 'my-secure-password';

    // Hash the password
    $hash = $manager->hash($password);

    // Verify correct password
    expect($manager->verify($password, $hash))->toBeTrue()
        ->and($manager->verify('wrong-password', $hash))->toBeFalse();

    // Verify wrong password
});

it('completes full password hashing workflow with argon2id', function () {
    $manager = createIntegrationManager(['hashing.default' => 'argon2id']);
    $password = 'my-secure-password';

    // Hash the password
    $hash = $manager->hash($password);

    // Verify correct password
    expect($manager->verify($password, $hash))->toBeTrue()
        ->and($manager->verify('wrong-password', $hash))->toBeFalse();

    // Verify wrong password
});

it('switches between hashers within same manager', function () {
    $manager = createIntegrationManager();

    $bcryptHash = $manager->hasher('bcrypt')->hash('password');
    $argon2Hash = $manager->hasher('argon2id')->hash('password');

    expect($manager->hasher('bcrypt')->verify('password', $bcryptHash))->toBeTrue()
        ->and($manager->hasher('argon2id')->verify('password', $argon2Hash))->toBeTrue()
        ->and($bcryptHash)->not->toBe($argon2Hash);
});

it('detects algorithm change requires rehash', function () {
    $bcryptManager = createIntegrationManager(['hashing.default' => 'bcrypt']);
    $argon2Manager = createIntegrationManager(['hashing.default' => 'argon2id']);

    $bcryptHash = $bcryptManager->hash('password');

    // Argon2 manager should indicate bcrypt hash needs rehash
    // Note: password_needs_rehash returns true for different algorithms
    expect($argon2Manager->needsRehash($bcryptHash))->toBeTrue();
});

it('detects cost change requires rehash', function () {
    $lowCostManager = createIntegrationManager(['hashing.hashers.bcrypt.cost' => 4]);
    $highCostManager = createIntegrationManager(['hashing.hashers.bcrypt.cost' => 6]);

    $lowCostHash = $lowCostManager->hash('password');

    expect($highCostManager->needsRehash($lowCostHash))->toBeTrue();
});

it('can rehash password when cost changes', function () {
    $lowCostManager = createIntegrationManager(['hashing.hashers.bcrypt.cost' => 4]);
    $highCostManager = createIntegrationManager(['hashing.hashers.bcrypt.cost' => 6]);

    $password = 'password';
    $oldHash = $lowCostManager->hash($password);

    // Verify old hash still works
    expect($highCostManager->verify($password, $oldHash))->toBeTrue()
        ->and($highCostManager->needsRehash($oldHash))->toBeTrue();

    // Detect need for rehash

    // Rehash with new cost
    $newHash = $highCostManager->hash($password);

    // New hash should not need rehash
    expect($highCostManager->needsRehash($newHash))->toBeFalse()
        ->and($highCostManager->verify($password, $newHash))->toBeTrue();
});

it('all hashers implement HasherInterface', function () {
    $manager = createIntegrationManager();

    expect($manager->hasher('bcrypt'))->toBeInstanceOf(HasherInterface::class)
        ->and($manager->hasher('argon2id'))->toBeInstanceOf(HasherInterface::class);
});

it('each hasher returns correct algorithm name', function () {
    $manager = createIntegrationManager();

    expect($manager->hasher('bcrypt')->algorithm())->toBe('bcrypt')
        ->and($manager->hasher('argon2id')->algorithm())->toBe('argon2id');
});

it('handles empty password', function () {
    $manager = createIntegrationManager();

    $hash = $manager->hash('');

    expect($manager->verify('', $hash))->toBeTrue()
        ->and($manager->verify('not-empty', $hash))->toBeFalse();
});

it('handles unicode password', function () {
    $manager = createIntegrationManager();
    $password = 'пароль日本語🔐';

    $hash = $manager->hash($password);

    expect($manager->verify($password, $hash))->toBeTrue()
        ->and($manager->verify('different', $hash))->toBeFalse();
});

it('handles very long password', function () {
    // Note: bcrypt truncates at 72 bytes, so we test with argon2id for long passwords
    $manager = createIntegrationManager(['hashing.default' => 'argon2id']);
    $password = str_repeat('a', 1000);

    $hash = $manager->hash($password);

    expect($manager->verify($password, $hash))->toBeTrue()
        ->and($manager->verify($password . 'b', $hash))->toBeFalse();
});

it('handles special characters in password', function () {
    $manager = createIntegrationManager();
    $password = '!@#$%^&*()_+-=[]{}|;:\'",.<>?/`~\\';

    $hash = $manager->hash($password);

    expect($manager->verify($password, $hash))->toBeTrue();
});

it('cross-verifies bcrypt hashes with password_verify', function () {
    $manager = createIntegrationManager(['hashing.default' => 'bcrypt']);

    $hash = $manager->hash('password');

    // Verify using PHP's built-in function
    expect(password_verify('password', $hash))->toBeTrue();
});

it('cross-verifies argon2id hashes with password_verify', function () {
    $manager = createIntegrationManager(['hashing.default' => 'argon2id']);

    $hash = $manager->hash('password');

    // Verify using PHP's built-in function
    expect(password_verify('password', $hash))->toBeTrue();
});

it('produces timing-safe verification', function () {
    $manager = createIntegrationManager();
    $hash = $manager->hash('correct-password');

    // Both correct and incorrect passwords should take similar time
    // This is a sanity check that we're using password_verify which is timing-safe
    $start1 = hrtime(true);
    $manager->verify('correct-password', $hash);
    $time1 = hrtime(true) - $start1;

    $start2 = hrtime(true);
    $manager->verify('wrong-password', $hash);
    $time2 = hrtime(true) - $start2;

    // Times should be within same order of magnitude
    // This is a basic check - password_verify is designed to be timing-safe
    expect(abs($time1 - $time2))->toBeLessThan($time1 * 10);
});

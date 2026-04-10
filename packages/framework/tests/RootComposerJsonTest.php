<?php

declare(strict_types=1);

$rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
$rootComposer = json_decode(file_get_contents($rootComposerPath), true);

$allPackages = [
    'marko/admin',
    'marko/admin-api',
    'marko/admin-auth',
    'marko/admin-panel',
    'marko/amphp',
    'marko/api',
    'marko/authentication',
    'marko/authentication-token',
    'marko/authorization',
    'marko/cache',
    'marko/cache-array',
    'marko/cache-file',
    'marko/cache-redis',
    'marko/cli',
    'marko/config',
    'marko/core',
    'marko/cors',
    'marko/database',
    'marko/database-mysql',
    'marko/database-pgsql',
    'marko/dev-server',
    'marko/encryption',
    'marko/encryption-openssl',
    'marko/env',
    'marko/errors',
    'marko/errors-advanced',
    'marko/errors-simple',
    'marko/filesystem',
    'marko/filesystem-local',
    'marko/filesystem-s3',
    'marko/framework',
    'marko/hashing',
    'marko/health',
    'marko/http',
    'marko/http-guzzle',
    'marko/log',
    'marko/log-file',
    'marko/mail',
    'marko/mail-log',
    'marko/mail-smtp',
    'marko/media',
    'marko/media-gd',
    'marko/media-imagick',
    'marko/notification',
    'marko/notification-database',
    'marko/pagination',
    'marko/pubsub',
    'marko/pubsub-pgsql',
    'marko/pubsub-redis',
    'marko/queue',
    'marko/queue-database',
    'marko/queue-rabbitmq',
    'marko/queue-sync',
    'marko/rate-limiting',
    'marko/routing',
    'marko/scheduler',
    'marko/search',
    'marko/security',
    'marko/session',
    'marko/session-database',
    'marko/session-file',
    'marko/sse',
    'marko/testing',
    'marko/translation',
    'marko/translation-file',
    'marko/validation',
    'marko/view',
    'marko/view-latte',
    'marko/webhook',
];

it('adds a require section entry for all 70 marko packages set to self.version', function () use ($rootComposer, $allPackages): void {
    expect($rootComposer)->toHaveKey('require');

    foreach ($allPackages as $package) {
        expect($rootComposer['require'])->toHaveKey($package)
            ->and($rootComposer['require'][$package])->toBe('self.version');
    }
});

it('does not have a replace section (path repos install as symlinks without it)', function () use ($rootComposer): void {
    expect($rootComposer)->not->toHaveKey('replace');
});

it('adds repositories section with path repos for all 70 packages', function () use ($rootComposer, $allPackages): void {
    expect($rootComposer)->toHaveKey('repositories');

    $repoUrls = array_column($rootComposer['repositories'], 'url');

    foreach ($allPackages as $package) {
        $packageName = str_replace('marko/', '', $package);
        expect(in_array("packages/$packageName", $repoUrls, true))->toBeTrue();
    }

    foreach ($rootComposer['repositories'] as $repo) {
        expect($repo)->toHaveKey('type')
            ->and($repo['type'])->toBe('path');
    }
});

it('removes all manual PSR-4 autoload entries for marko packages', function () use ($rootComposer): void {
    if (!isset($rootComposer['autoload']['psr-4'])) {
        expect(true)->toBeTrue();
        return;
    }

    foreach ($rootComposer['autoload']['psr-4'] as $namespace => $path) {
        expect(str_starts_with($namespace, 'Marko\\'))->toBeFalse();
    }
});

it('keeps autoload-dev entries for test namespaces (Composer does not merge autoload-dev from dependencies)', function () use ($rootComposer, $allPackages): void {
    // autoload-dev must remain in root: Composer only applies a package's autoload-dev
    // when it is the root package, so test namespaces for all monorepo packages must
    // be declared here to be discoverable when running the test suite.
    expect($rootComposer)->toHaveKey('autoload-dev')
        ->and($rootComposer['autoload-dev'])->toHaveKey('psr-4');

    $devPsr4 = $rootComposer['autoload-dev']['psr-4'];
    $hasAtLeastOneTestNamespace = array_any(
        array_keys($devPsr4),
        fn (string $ns): bool => str_ends_with($ns, 'Tests\\'),
    );
    expect($hasAtLeastOneTestNamespace)->toBeTrue();
});

it('removes the autoload files entry for packages/env/src/functions.php', function () use ($rootComposer): void {
    $files = $rootComposer['autoload']['files'] ?? [];

    expect(in_array('packages/env/src/functions.php', $files, true))->toBeFalse();
});

it('preserves existing require (php, ext-*) and require-dev (third-party) entries', function () use ($rootComposer): void {
    expect($rootComposer['require'])->toHaveKey('php')
        ->and($rootComposer['require']['php'])->toBe('^8.5')
        ->and($rootComposer['require'])->toHaveKey('ext-pdo')
        ->and($rootComposer['require'])->toHaveKey('ext-fileinfo')
        ->and($rootComposer['require'])->toHaveKey('ext-gd')
        ->and($rootComposer['require'])->toHaveKey('ext-imagick');

    $expectedDevPackages = [
        'amphp/postgres',
        'amphp/redis',
        'aws/aws-sdk-php',
        'friendsofphp/php-cs-fixer',
        'guzzlehttp/guzzle',
        'latte/latte',
        'pestphp/pest',
        'php-amqplib/php-amqplib',
        'predis/predis',
        'rector/rector',
        'slevomat/coding-standard',
        'squizlabs/php_codesniffer',
    ];

    foreach ($expectedDevPackages as $package) {
        expect($rootComposer['require-dev'])->toHaveKey($package);
    }
});

it('preserves scripts, config, and other root-level settings', function () use ($rootComposer): void {
    expect($rootComposer)->toHaveKey('scripts')
        ->and($rootComposer['scripts'])->toHaveKey('test')
        ->and($rootComposer['scripts'])->toHaveKey('cs:check')
        ->and($rootComposer['scripts'])->toHaveKey('cs:fix')
        ->and($rootComposer['scripts'])->toHaveKey('rector');

    expect($rootComposer)->toHaveKey('config')
        ->and($rootComposer['config'])->toHaveKey('sort-packages')
        ->and($rootComposer['config'])->toHaveKey('allow-plugins');
});

it('keeps minimum-stability as stable (replace bypasses stability checks for replaced packages)', function () use ($rootComposer): void {
    expect($rootComposer)->toHaveKey('minimum-stability')
        ->and($rootComposer['minimum-stability'])->toBe('stable');
});

it('keeps prefer-stable as true', function () use ($rootComposer): void {
    expect($rootComposer)->toHaveKey('prefer-stable')
        ->and($rootComposer['prefer-stable'])->toBeTrue();
});

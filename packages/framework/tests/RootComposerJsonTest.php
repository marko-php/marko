<?php

declare(strict_types=1);

$rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
$rootComposer = json_decode(file_get_contents($rootComposerPath), true);

$packagesRoot = dirname(__DIR__, 2);

// Every monorepo package, keyed by directory name, derived from packages/*/composer.json
// so a new package is covered without editing this test.
$packageComposers = [];

foreach (glob($packagesRoot . '/*/composer.json') as $packageComposerPath) {
    $packageComposers[basename(dirname($packageComposerPath))] = json_decode(
        file_get_contents($packageComposerPath),
        true,
    );
}

ksort($packageComposers);

// The root requires every library package. The skeleton (type:project) is the template
// `composer create-project` copies, not a dependency, so it is not required.
$allPackages = array_values(array_map(
    fn (array $composer): string => $composer['name'],
    array_filter(
        $packageComposers,
        fn (array $composer): bool => ($composer['type'] ?? 'library') !== 'project',
    ),
));

it('finds the monorepo packages to check', function () use ($allPackages): void {
    expect($allPackages)->not->toBeEmpty()
        ->and($allPackages)->toContain('marko/framework');
});

it(
    'requires every library package in packages/ at self.version, in require or require-dev',
    function () use ($rootComposer, $allPackages): void {
        expect($rootComposer)->toHaveKey('require');

        // Dev-only tools (marko/debugbar) sit in require-dev; everything else in require.
        $rootRequires = $rootComposer['require'] + ($rootComposer['require-dev'] ?? []);

        foreach ($allPackages as $package) {
            expect($rootRequires)->toHaveKey($package)
                ->and($rootRequires[$package])->toBe('self.version');
        }
    },
);

it(
    'requires no marko package twice or one that is missing from packages/',
    function () use ($rootComposer, $allPackages): void {
        $isMarkoPackage = fn (string $package): bool => str_starts_with($package, 'marko/');
        $required = array_filter(array_keys($rootComposer['require']), $isMarkoPackage);
        $requiredDev = array_filter(array_keys($rootComposer['require-dev'] ?? []), $isMarkoPackage);

        expect(array_values(array_intersect($required, $requiredDev)))->toBe([])
            ->and(array_values(array_diff([...$required, ...$requiredDev], $allPackages)))->toBe([]);
    },
);

it(
    'does not have a replace section (path repos install as symlinks without it)',
    function () use ($rootComposer): void {
        expect($rootComposer)->not->toHaveKey('replace');
    },
);

it(
    'has a path repository for every directory in packages/',
    function () use ($rootComposer, $packageComposers): void {
        expect($rootComposer)->toHaveKey('repositories');

        $repoUrls = array_column($rootComposer['repositories'], 'url');

        foreach (array_keys($packageComposers) as $directory) {
            expect(in_array("packages/$directory", $repoUrls, true))
                ->toBeTrue("Root composer.json has no path repository for packages/$directory");
        }

        foreach ($rootComposer['repositories'] as $repo) {
            expect($repo)->toHaveKey('type')
                ->and($repo['type'])->toBe('path');
        }
    },
);

it('removes all manual PSR-4 autoload entries for marko packages', function () use ($rootComposer): void {
    if (!isset($rootComposer['autoload']['psr-4'])) {
        expect(true)->toBeTrue();

        return;
    }

    foreach (array_keys($rootComposer['autoload']['psr-4']) as $namespace) {
        expect(str_starts_with($namespace, 'Marko\\'))->toBeFalse();
    }
});

it(
    'keeps autoload-dev entries for test namespaces (Composer does not merge autoload-dev from dependencies)',
    function () use ($rootComposer, $allPackages): void {
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
    },
);

it('removes the autoload files entry for packages/env/src/functions.php', function () use ($rootComposer): void {
    $files = $rootComposer['autoload']['files'] ?? [];

    expect(in_array('packages/env/src/functions.php', $files, true))->toBeFalse();
});

it(
    'preserves existing require (php, ext-*) and require-dev (third-party) entries',
    function () use ($rootComposer): void {
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
    },
);

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

it(
    'keeps minimum-stability as stable (replace bypasses stability checks for replaced packages)',
    function () use ($rootComposer): void {
        expect($rootComposer)->toHaveKey('minimum-stability')
            ->and($rootComposer['minimum-stability'])->toBe('stable');
    },
);

it('keeps prefer-stable as true', function () use ($rootComposer): void {
    expect($rootComposer)->toHaveKey('prefer-stable')
        ->and($rootComposer['prefer-stable'])->toBeTrue();
});

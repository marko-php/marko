<?php

declare(strict_types=1);

use Marko\Tests\Support\UnsupportedFeature\UnsupportedFeatureDetector;
use Marko\Tests\Support\UnsupportedFeature\UnsupportedFeatureDiscovery;

require_once __DIR__ . '/Support/UnsupportedFeature/UnsupportedFeatureDetector.php';
require_once __DIR__ . '/Support/UnsupportedFeature/UnsupportedFeatureDiscovery.php';

$fixturesRoot = __DIR__ . '/Fixtures/UnsupportedFeature';

// Parents first: the fixtures are not autoloaded.
foreach ([
    'ViolatingByNameException',
    'ViolatingByMessageException',
    'InheritingException',
    'AllowlistedException',
    'CompliantBaseException',
    'UnsupportedCompliantFeatureException',
    'UnsupportedUnmarkedException',
] as $fixtureName) {
    require_once $fixturesRoot . '/' . $fixtureName . '.php';
}

/**
 * Factories that match Rule A or C but are not driver capability. Each entry must still exist
 * and still match, or the test fails.
 */
const UNSUPPORTED_FEATURE_ALLOWLIST = [
    // Environment or extension requirement, not something a driver can lack.
    'Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException::signalsUnsupported',
    // Environment requirement: the installed SQLite library is too old.
    'Marko\Database\Sqlite\Exceptions\ConnectionException::unsupportedVersion',
    // Framework-wide limit that applies to every driver.
    'Marko\Database\Exceptions\BatchInsertException::companionsNotSupported',
    // Input validation: the caller passed a stream target that cannot be streamed.
    'Marko\Log\Stream\Exceptions\InvalidStreamTargetException::unsupported',
    // Input validation: a route parameter type the router never handles.
    'Marko\Routing\Exceptions\RouteException::unsupportedParameterType',
    // Input validation: a default value the plugin system cannot represent.
    'Marko\Core\Exceptions\PluginException::unsupportedDefaultValue',
    // Usage validation: the lock operation is not one of the known operations.
    'Marko\Database\Exceptions\LockException::unsupportedOperation',
    // Usage validation: extension chaining is a framework-wide limit.
    'Marko\Database\Exceptions\EntityException::chainedExtensionNotSupported',
    // Input validation: the offload header value is malformed.
    'Marko\Routing\Exceptions\FileResponseException::invalidOffloadHeader',
];

$detector = new UnsupportedFeatureDetector();
$fixtureAllowlist = ['Marko\Tests\Fixtures\UnsupportedFeature\AllowlistedException::unsupportedOption'];

it(
    'finds no unsupported-feature factory that bypasses the interface in package sources',
    function () use ($detector): void {
        $files = (new UnsupportedFeatureDiscovery())->discover(dirname(__DIR__) . '/packages');
        $violations = $detector->scanFactories($files, UNSUPPORTED_FEATURE_ALLOWLIST);

        expect($files)->not->toBeEmpty()
            ->and($detector->describe($violations))->toBe('');
    },
);

it('makes every Unsupported exception class implement the interface', function () use ($detector): void {
    $files = (new UnsupportedFeatureDiscovery())->discover(dirname(__DIR__) . '/packages');
    $violations = $detector->scanClassNames($files);

    expect($detector->describe($violations))->toBe('');
});

it('discovers exception classes outside an Exceptions directory', function () use ($detector): void {
    $files = (new UnsupportedFeatureDiscovery())->discover(dirname(__DIR__) . '/packages');
    $classes = $detector->declaredClasses($files);

    expect($classes)->toHaveKey('Marko\Mail\Exception\TransportException')
        ->and($classes)->toHaveKey('Marko\Core\Plugin\PluginArgumentCountException')
        ->and(array_filter($files, fn (string $file): bool => str_contains($file, '/tests/Fixtures/')))
        ->toBeEmpty();
});

it(
    'flags a fixture factory whose name says unsupported and returns a plain package exception',
    function () use ($detector, $fixturesRoot): void {
        $violations = $detector->scanFactories([$fixturesRoot . '/ViolatingByNameException.php'], []);

        expect($violations)->toHaveCount(1)
            ->and($violations[0]['subject'])
            ->toBe('Marko\Tests\Fixtures\UnsupportedFeature\ViolatingByNameException::featureUnsupported')
            ->and($violations[0]['rule'])->toBe('A');
    },
);

it(
    'flags a fixture factory whose message says not supported and returns a plain package exception',
    function () use ($detector, $fixturesRoot): void {
        $violations = $detector->scanFactories([$fixturesRoot . '/ViolatingByMessageException.php'], []);

        expect($violations)->toHaveCount(1)
            ->and($violations[0]['subject'])
            ->toBe('Marko\Tests\Fixtures\UnsupportedFeature\ViolatingByMessageException::noTransactions')
            ->and($violations[0]['rule'])->toBe('C');
    },
);

it(
    'accepts a fixture factory that returns an implementing subclass',
    function () use ($detector, $fixturesRoot): void {
        $files = [
            $fixturesRoot . '/CompliantBaseException.php',
            $fixturesRoot . '/UnsupportedCompliantFeatureException.php',
        ];

        expect($detector->scanFactories($files, []))->toBeEmpty()
            ->and($detector->scanClassNames($files))->toBeEmpty();
    },
);

it('does not report factories a subclass inherits from its parent', function () use ($detector, $fixturesRoot): void {
    expect($detector->scanFactories([$fixturesRoot . '/InheritingException.php'], []))->toBeEmpty()
        ->and($detector->scanFactories([
            $fixturesRoot . '/ViolatingByNameException.php',
            $fixturesRoot . '/InheritingException.php',
        ], []))->toHaveCount(1);
});

it('accepts an allowlisted fixture factory', function () use ($detector, $fixturesRoot, $fixtureAllowlist): void {
    $files = [$fixturesRoot . '/AllowlistedException.php'];

    expect($detector->scanFactories($files, []))->toHaveCount(1)
        ->and($detector->scanFactories($files, $fixtureAllowlist))->toBeEmpty();
});

it(
    'fails when an allowlist entry no longer exists',
    function () use ($detector, $fixturesRoot, $fixtureAllowlist): void {
        $files = [$fixturesRoot . '/AllowlistedException.php'];
        $violations = $detector->scanFactories(
            $files,
            [...$fixtureAllowlist, 'Marko\Gone\GoneException::unsupported'],
        );

        expect($violations)->toHaveCount(1)
            ->and($violations[0]['rule'])->toBe('allowlist')
            ->and($violations[0]['message'])->toContain('Marko\Gone\GoneException::unsupported');
    },
);

it(
    'flags an Unsupported exception class that does not implement the interface',
    function () use ($detector, $fixturesRoot): void {
        $violations = $detector->scanClassNames([$fixturesRoot . '/UnsupportedUnmarkedException.php']);

        expect($violations)->toHaveCount(1)
            ->and($violations[0]['rule'])->toBe('B');
    },
);

it(
    'names the violating class and method and the fix in the failure message',
    function () use ($detector, $fixturesRoot): void {
        $violations = $detector->scanFactories([$fixturesRoot . '/ViolatingByNameException.php'], []);

        expect($detector->describe($violations))
            ->toContain('Marko\Tests\Fixtures\UnsupportedFeature\ViolatingByNameException::featureUnsupported')
            ->toContain('Rule A')
            ->toContain('Unsupported{Thing}FeatureException')
            ->toContain('UnsupportedFeatureException::forDriver()')
            ->toContain('allowlist');
    },
);

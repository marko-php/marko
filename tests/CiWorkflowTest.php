<?php

declare(strict_types=1);

$ciPath = dirname(__DIR__) . '/.github/workflows/ci.yml';
$nightlyPath = dirname(__DIR__) . '/.github/workflows/nightly.yml';
$ci = file_exists($ciPath) ? file_get_contents($ciPath) : '';
$nightly = file_exists($nightlyPath) ? file_get_contents($nightlyPath) : '';

it('creates a CI workflow at .github/workflows/ci.yml', function () use ($ciPath): void {
    expect(file_exists($ciPath))->toBeTrue('.github/workflows/ci.yml must exist');
});

it('gates every pull request, not just those touching certain paths', function () use ($ci): void {
    // A paths: filter here would let a PR skip the gate entirely.
    $trigger = substr($ci, 0, (int) strpos($ci, 'jobs:'));

    expect($trigger)
        ->toContain('pull_request')
        ->not->toContain('paths:');
});

it('also runs on pushes to develop and main', function () use ($ci): void {
    expect($ci)
        ->toContain('branches:')
        ->toContain('- develop')
        ->toContain('- main');
});

it('runs tests, lint, and static analysis as separate jobs', function () use ($ci): void {
    expect($ci)
        ->toContain('name: Tests')
        ->toContain('name: Lint')
        ->toContain('name: Static analysis')
        ->toContain('composer test')
        ->toContain('phpcs --standard=phpcs.xml')
        ->toContain('php-cs-fixer fix --config=.php-cs-fixer.php --dry-run')
        ->toContain('composer phpstan');
});

it(
    'runs the integration-services group in its own job against postgres and redis services',
    function () use ($ci): void {
        $job = substr($ci, (int) strpos($ci, 'name: Integration'));
    
        expect($ci)->toContain('name: Integration')
            ->and($job)
            ->toContain('services:')
            ->toContain('image: postgres:17')
            ->toContain('image: redis:7')
            ->toContain('- 5432:5432')
            ->toContain('- 6379:6379')
            ->toContain('pdo_pgsql')
            ->toContain('DB_HOST: 127.0.0.1')
            ->toContain('REDIS_HOST: 127.0.0.1')
            ->toContain('run: composer test:integration');
    }
);

it('health-checks the integration services before running the suite', function () use ($ci): void {
    $job = substr($ci, (int) strpos($ci, 'name: Integration'));

    expect($job)
        ->toContain('--health-cmd "pg_isready')
        ->toContain('--health-cmd "redis-cli ping"');
});

it('fails the integration job instead of skipping when services are unreachable', function () use ($ci): void {
    // Without this the job would go green with every case skipped.
    $job = substr($ci, (int) strpos($ci, 'name: Integration'));

    expect($job)->toContain("MARKO_INTEGRATION_REQUIRED: '1'");
});

it('runs a mysql service with a health check in the integration job', function () use ($ci): void {
    $job = substr($ci, (int) strpos($ci, 'name: Integration'));

    expect($job)
        ->toContain('image: mysql:8.4')
        ->toContain('- 3306:3306')
        ->toContain('MYSQL_DATABASE: marko_test')
        ->toContain('--health-cmd "mysqladmin ping');
});

it('installs pdo_mysql alongside pdo_pgsql in the integration job', function () use ($ci): void {
    $job = substr($ci, (int) strpos($ci, 'name: Integration'));

    expect($job)->toContain('extensions: sockets, pdo_pgsql, pdo_mysql');
});

it(
    'points the pgsql driver integration tests at the postgres service with their own database',
    function () use ($ci): void {
        // The fixture suite drops marko_integration before every case, so the
        // driver tests need a database of their own, created before the run.
        $job = substr($ci, (int) strpos($ci, 'name: Integration'));
        $createdAt = strpos($job, 'createdb -U marko marko_test');
        $suiteAt = strpos($job, 'run: composer test:integration');

        expect($job)
            ->toContain('MARKO_TEST_PGSQL_HOST: 127.0.0.1')
            ->toContain('MARKO_TEST_PGSQL_PORT: 5432')
            ->toContain('MARKO_TEST_PGSQL_DATABASE: marko_test')
            ->toContain('MARKO_TEST_PGSQL_USERNAME: marko')
            ->toContain('MARKO_TEST_PGSQL_PASSWORD: marko')
            ->and($createdAt)->not->toBeFalse()
            ->and($createdAt)->toBeLessThan($suiteAt);
    },
);

it('runs the queue and session MySQL suites against both MariaDB services', function () use ($ci): void {
    $steps = array_values(array_filter(
        preg_split('/\n(?=      - name: )/', $ci) ?: [],
        fn (string $step): bool => str_contains($step, 'MARKO_TEST_MYSQL_SERVER: mariadb'),
    ));
    $suites = [
        'packages/queue-database/tests/Integration/MySqlRoundTripTest.php',
        'packages/session-database/tests/Integration/MySql',
        'tests/Integration/App/QueueSessionTablesMySqlTest.php',
    ];

    expect($steps)->toHaveCount(2)
        ->and(array_filter(
            $steps,
            fn (string $step): bool => array_all($suites, fn (string $suite): bool => str_contains($step, $suite)),
        ))->toHaveCount(2);
})->issue(337);

it('runs the search MySQL suite against both MariaDB services', function () use ($ci): void {
    $steps = array_values(array_filter(
        preg_split('/\n(?=      - name: )/', $ci) ?: [],
        fn (string $step): bool => str_contains($step, 'MARKO_TEST_MYSQL_SERVER: mariadb'),
    ));

    expect($steps)->toHaveCount(2)
        ->and(array_filter(
            $steps,
            fn (string $step): bool => str_contains($step, 'packages/search/tests/Integration/MySql'),
        ))->toHaveCount(2);
})->issue(338);

it('points the mysql driver integration tests at the mysql service', function () use ($ci): void {
    $job = substr($ci, (int) strpos($ci, 'name: Integration'));

    expect($job)
        ->toContain('MARKO_TEST_MYSQL_HOST: 127.0.0.1')
        ->toContain('MARKO_TEST_MYSQL_PORT: 3306')
        ->toContain('MARKO_TEST_MYSQL_DATABASE: marko_test')
        ->toContain('MARKO_TEST_MYSQL_USERNAME: root')
        ->toContain('MARKO_TEST_MYSQL_PASSWORD: marko');
});

it('defines every integration service in the local compose file', function (): void {
    $compose = (string) file_get_contents(dirname(__DIR__) . '/tests/Integration/compose.yml');
    $init = (string) file_get_contents(
        dirname(__DIR__) . '/tests/Integration/postgres-init/01-create-driver-test-database.sql',
    );

    expect($compose)
        ->toContain('image: postgres:17')
        ->toContain('image: mysql:8.4')
        ->toContain('image: redis:7')
        ->toContain('MYSQL_DATABASE: marko_test')
        ->toContain('./postgres-init:/docker-entrypoint-initdb.d')
        ->and($init)->toContain('CREATE DATABASE marko_test');
});

it('exposes a composer test:integration script scoped to the integration-services group', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    expect($composer['scripts'])->toHaveKey('test:integration')
        ->and($composer['scripts']['test:integration'])->toContain('--group=integration-services')
        ->and($composer['scripts']['test'])->not->toContain('integration-services');
});

it('pins PHP 8.5 in every job', function () use ($ci): void {
    expect(substr_count($ci, "php-version: '8.5'"))->toBe(substr_count($ci, 'runs-on: ubuntu-latest'));
});

it('pins actions to a major version rather than a floating ref', function () use ($ci, $nightly): void {
    preg_match_all('/uses: (\S+)/', $ci . $nightly, $matches);

    expect($matches[1])->not->toBeEmpty()
        ->and($matches[1])->each->toMatch('/@v\d+$/');
});

it('runs the destructive group on a schedule instead of on every PR', function () use ($ci, $nightly): void {
    expect($nightly)
        ->toContain('schedule:')
        ->toContain('cron:')
        ->toContain('composer test:all')
        ->and($ci)->toContain('composer test')
        ->and($ci)->not->toContain('test:all');
});

it(
    'provides a redis service so the redis integration suites never silently skip',
    function () use ($ci, $nightly): void {
        $redisService = "    services:\n      # Redis integration suites (packages/cache-redis, packages/ratelimiter)\n"
            . "      # skip without a server; this keeps them running on every build.\n"
            . "      redis:\n        image: redis:7-alpine\n        ports:\n          - 6379:6379\n";

        expect($ci)->toContain("    name: Tests\n    runs-on: ubuntu-latest\n" . $redisService)
            ->and($nightly)->toContain($redisService);
    },
);

it('installs the roadrunner binary in the nightly workflow', function () use ($nightly): void {
    // Without this, packages/roadrunner's end-to-end suite finds no `rr`
    // binary on the nightly runner and every one of its security-critical
    // isolation assertions silently skips, defeating the plan's own stated
    // mitigation of running "locally and nightly" — this assertion exists
    // so a future edit cannot quietly drop the step and reintroduce that.
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    $dependenciesInstalledAt = strpos($nightly, 'ramsey/composer-install');
    $binaryInstalledAt = strpos($nightly, 'vendor/bin/rr get-binary');
    $testsRunAt = strpos($nightly, 'composer test:all');

    expect($composer['require-dev'])->toHaveKey('spiral/roadrunner-cli')
        ->and($dependenciesInstalledAt)->not->toBeFalse()
        ->and($binaryInstalledAt)->not->toBeFalse()
        ->and($testsRunAt)->not->toBeFalse()
        ->and($binaryInstalledAt)->toBeGreaterThan($dependenciesInstalledAt)
        ->and($binaryInstalledAt)->toBeLessThan($testsRunAt);
});

it('exposes composer phpstan and composer ci scripts the workflow depends on', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    expect($composer['scripts'])->toHaveKey('phpstan')
        ->and($composer['scripts'])->toHaveKey('ci')
        ->and($composer['scripts']['ci'])->toContain('@test')
        ->and($composer['scripts']['ci'])->toContain('@phpstan');
});

it('excludes deliberately-unparseable fixtures from both linters', function (): void {
    // php-cs-fixer lints before fixing and exits 4 on invalid PHP even with nothing to change;
    // phpcs aborts on the file. Either would fail the Lint job forever.
    $csFixer = file_get_contents(dirname(__DIR__) . '/.php-cs-fixer.php');
    $phpcs = file_get_contents(dirname(__DIR__) . '/phpcs.xml');

    $brokenFixtures = [
        'packages/config/tests/Unit/fixtures/syntax-error.php',
        'packages/codeindexer/tests/Fixtures/AttributeFixtures/src/Broken/SyntaxError.php',
        'packages/codeindexer/tests/Fixtures/ConfigFixtures/module-d/config/broken.php',
    ];

    foreach ($brokenFixtures as $fixture) {
        expect(file_exists(dirname(__DIR__) . '/' . $fixture))
            ->toBeTrue("$fixture should still exist")
            ->and($csFixer)->toContain(str_replace('packages/', '', $fixture));
    }

    expect($phpcs)->toContain('src/Broken/');
});

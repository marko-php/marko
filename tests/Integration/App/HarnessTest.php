<?php

declare(strict_types=1);

// The harness itself needs no services, so these run in every `composer test`.

it('explains how to start the services when DB_HOST is not set', function (): void {
    expect(integrationServicesProblem(['REDIS_HOST' => '127.0.0.1']))
        ->toContain('DB_HOST is not set')
        ->toContain('docker compose -f tests/Integration/compose.yml up -d');
});

it('explains how to start the services when REDIS_HOST is not set', function (): void {
    expect(integrationServicesProblem(['DB_HOST' => '127.0.0.1', 'DB_PORT' => '1']))
        ->toContain('Postgres not reachable at 127.0.0.1:1');

    $listener = stream_socket_server('tcp://127.0.0.1:0');
    $port = (string) parse_url('tcp://' . stream_socket_get_name($listener, false), PHP_URL_PORT);

    expect(integrationServicesProblem(['DB_HOST' => '127.0.0.1', 'DB_PORT' => $port]))
        ->toContain('REDIS_HOST is not set')
        ->toContain('docker compose -f tests/Integration/compose.yml up -d');

    fclose($listener);
});

it('reports an unreachable host and port with the compose command', function (): void {
    expect(integrationServicesProblem(['DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'REDIS_HOST' => '127.0.0.1']))
        ->toContain('Postgres not reachable at 127.0.0.1:1')
        ->toContain('docker compose -f tests/Integration/compose.yml up -d');
});

it('skips rather than fails when the services are missing and not required', function (): void {
    expect(integrationServicesSkipReasonFor([]))->toContain('DB_HOST is not set');
});

it('throws instead of skipping when MARKO_INTEGRATION_REQUIRED is set', function (): void {
    expect(fn () => integrationServicesSkipReasonFor(['MARKO_INTEGRATION_REQUIRED' => '1']))
        ->toThrow(RuntimeException::class, 'MARKO_INTEGRATION_REQUIRED is set but the services are unusable');
});

it('suffixes the database name with the parallel test token', function (): void {
    expect(integrationDatabaseName([]))->toBe('marko_integration')
        ->and(integrationDatabaseName(['DB_DATABASE' => 'app']))->toBe('app')
        ->and(integrationDatabaseName(['TEST_TOKEN' => '3']))->toBe('marko_integration_3');
});

it('builds a fixture project whose vendor modules link to the monorepo packages', function (): void {
    $project = buildIntegrationProject();

    try {
        $root = dirname(__DIR__, 3);

        foreach (INTEGRATION_MODULES as $module) {
            expect(realpath("$project/vendor/marko/$module"))->toBe(realpath("$root/packages/$module"));
        }

        expect(file_exists("$project/app/integration/composer.json"))->toBeTrue()
            ->and(file_exists("$project/config/database.php"))->toBeTrue()
            ->and(require "$project/vendor/autoload.php")->toBeInstanceOf(Composer\Autoload\ClassLoader::class);
    } finally {
        removeIntegrationProject($project);
    }

    expect(is_dir($project))->toBeFalse()
        ->and(is_dir(dirname(__DIR__, 3) . '/packages/core'))->toBeTrue();
});

it('tags a test with each owning ticket from the hand-off table', function (): void {
    // The #187 hand-off table. Every ticket's behaviour is proven by a real
    // test tagged ->issue(N).
    $tickets = [159, 160, 161, 162, 163, 164, 165, 166, 167, 168, 169, 170, 171, 173, 176, 177];
    $source = '';

    foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
        $source .= file_get_contents($file);
    }

    foreach ($tickets as $ticket) {
        expect($source)->toMatch("/->issue\\($ticket\\)/");
    }
});

it('keeps no todo rows in the integration suite', function (): void {
    // A todo claims nothing; the bug it names is either fixed (so the test
    // can be real) or belongs in the ticket that fixes it (#226).
    $todos = [];

    foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
        if (preg_match('/->\s*todo\s*\(/', (string) file_get_contents($file)) === 1) {
            $todos[] = basename($file);
        }
    }

    expect($todos)->toBe([])
        ->and(file_exists(__DIR__ . '/KnownGapsTest.php'))->toBeFalse();
});

it('passes the script name and command ahead of the arguments to the command runner', function (): void {
    $input = integrationCommandInput('queue:retry', ['5', '--queue=default', '--once']);

    // Since #184 getArguments() holds positionals only; options are parsed apart.
    expect($input->getCommand())->toBe('queue:retry')
        ->and($input->getArguments())->toBe(['5'])
        ->and($input->getOption('queue'))->toBe('default')
        ->and($input->hasOption('once'))->toBeTrue();
});

it('links only the integration module set into the fixture vendor directory', function (): void {
    $source = sys_get_temp_dir() . '/marko-integration-source-' . bin2hex(random_bytes(4));
    mkdir("$source/config", 0777, true);
    file_put_contents("$source/config/example.php", "<?php\n\nreturn [];\n");

    $project = buildIntegrationProject($source);

    try {
        $linked = array_values(array_diff(scandir("$project/vendor/marko"), ['.', '..']));
        sort($linked);
        $expected = INTEGRATION_MODULES;
        sort($expected);

        expect($linked)->toBe($expected)
            ->and(file_exists("$project/config/example.php"))->toBeTrue();
    } finally {
        removeIntegrationProject($project);
        removeIntegrationProject($source);
    }
});

it('removes the temp project without following vendor symlinks into the monorepo packages', function (): void {
    $target = sys_get_temp_dir() . '/marko-integration-target-' . bin2hex(random_bytes(4));
    mkdir($target);
    file_put_contents("$target/keep.txt", 'keep');

    $project = sys_get_temp_dir() . '/marko-integration-project-' . bin2hex(random_bytes(4));
    mkdir("$project/vendor/marko", 0777, true);
    symlink($target, "$project/vendor/marko/linked");

    removeIntegrationProject($project);

    expect(file_exists($project))->toBeFalse()
        ->and(file_get_contents("$target/keep.txt"))->toBe('keep');

    removeIntegrationProject($target);
});

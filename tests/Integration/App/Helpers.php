<?php

declare(strict_types=1);

/*
 * Harness for the `integration-services` group: a real fixture application
 * booted through Application::boot() against real Postgres and Redis.
 *
 * Every test in the group calls integrationServicesSkipReason() (via
 * bootIntegrationApp() or directly) before touching a service. With no
 * services configured the test is skipped with instructions; in CI
 * (MARKO_INTEGRATION_REQUIRED=1) the same condition is a failure, so the
 * Integration job can never go green by skipping everything.
 */

use Marko\Core\Application;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use PHPUnit\Framework\TestCase;

const INTEGRATION_COMPOSE_COMMAND = 'docker compose -f tests/Integration/compose.yml up -d';

/**
 * Monorepo packages installed into the fixture's vendor/marko/ directory.
 * Each entry is symlinked to packages/{name}, so the package's real
 * composer.json and module.php wiring run during boot.
 */
const INTEGRATION_MODULES = [
    'authentication',
    'authentication-token',
    'authorization',
    'cache',
    'cache-redis',
    'cli',
    'clock',
    'config',
    'core',
    'cors',
    'database',
    'database-pgsql',
    'encryption',
    'encryption-openssl',
    'errors',
    'errors-simple',
    'queue',
    'queue-database',
    'ratelimiter',
    'redis',
    'routing',
    'scheduler',
    'security',
    'session',
    'session-database',
    'validation',
];

function integrationFixturePath(): string
{
    return __DIR__ . '/Fixture';
}

/**
 * Why the integration services cannot be used with the given environment,
 * or null when Postgres and Redis are both configured and reachable.
 *
 * @param array<string, string> $env
 */
function integrationServicesProblem(
    array $env,
): ?string {
    $services = [
        'Postgres' => ['DB_HOST', 'DB_PORT', '5432'],
        'Redis' => ['REDIS_HOST', 'REDIS_PORT', '6379'],
    ];

    foreach ($services as $name => [$hostVar, $portVar, $defaultPort]) {
        $host = $env[$hostVar] ?? '';

        if ($host === '') {
            return "$hostVar is not set, so the integration-services group cannot reach $name. "
                . 'Start the services with `' . INTEGRATION_COMPOSE_COMMAND . '` and export '
                . 'DB_HOST=127.0.0.1 REDIS_HOST=127.0.0.1 (see .claude/testing.md).';
        }

        $port = (int) ($env[$portVar] ?? $defaultPort);

        // fsockopen() reports a refused connection as both a return value and
        // a warning; the return value is all we need, so the warning is muted.
        set_error_handler(static fn (): bool => true);

        try {
            $socket = fsockopen($host, $port, $errorCode, $errorMessage, 1.0);
        } finally {
            restore_error_handler();
        }

        if ($socket === false) {
            return "$name not reachable at $host:$port ($errorMessage); run `" . INTEGRATION_COMPOSE_COMMAND . '`.';
        }

        fclose($socket);
    }

    return null;
}

/**
 * @param array<string, string> $env
 */
function integrationServicesRequired(
    array $env,
): bool {
    return in_array(strtolower($env['MARKO_INTEGRATION_REQUIRED'] ?? ''), ['1', 'true', 'yes'], true);
}

/**
 * The skip reason for the given environment. Throws instead of returning a
 * reason when the environment demands the services (CI).
 *
 * @param array<string, string> $env
 * @throws RuntimeException
 */
function integrationServicesSkipReasonFor(
    array $env,
): ?string {
    $problem = integrationServicesProblem($env);

    if ($problem !== null && integrationServicesRequired($env)) {
        throw new RuntimeException("MARKO_INTEGRATION_REQUIRED is set but the services are unusable: $problem");
    }

    return $problem;
}

/**
 * The skip reason for this process's real environment, probed once.
 *
 * @throws RuntimeException
 */
function integrationServicesSkipReason(): ?string
{
    static $probed = false;
    static $reason = null;

    if (!$probed) {
        $reason = integrationServicesSkipReasonFor(getenv());
        $probed = true;
    }

    return $reason;
}

/**
 * The database this process uses. Parallel workers (paratest sets
 * TEST_TOKEN) each get their own database so they never clobber each other.
 *
 * @param array<string, string> $env
 */
function integrationDatabaseName(
    array $env,
): string {
    $name = ($env['DB_DATABASE'] ?? '') !== '' ? $env['DB_DATABASE'] : 'marko_integration';
    $token = $env['TEST_TOKEN'] ?? '';

    return $token !== '' ? $name . '_' . preg_replace('/\W/', '', $token) : $name;
}

/**
 * Drop and recreate this process's database so every test starts from an
 * empty schema.
 *
 * @throws PDOException
 */
function resetIntegrationDatabase(
    ?string $database = null,
): void {
    $env = getenv();
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%d;dbname=postgres', $env['DB_HOST'], (int) ($env['DB_PORT'] ?? 5432)),
        $env['DB_USERNAME'] ?? 'marko',
        $env['DB_PASSWORD'] ?? 'marko',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $database ??= integrationDatabaseName($env);

    $pdo->exec("DROP DATABASE IF EXISTS \"$database\" WITH (FORCE)");
    $pdo->exec("CREATE DATABASE \"$database\"");
}

/**
 * Copy the fixture into a fresh temporary project and install the
 * monorepo packages into its vendor/marko/ directory as symlinks. Each test
 * gets its own copy, so generated files and storage never touch the repo.
 *
 * @throws RuntimeException
 */
function buildIntegrationProject(
    ?string $fixturePath = null,
    array $modules = INTEGRATION_MODULES,
): string {
    $root = dirname(__DIR__, 3);
    $project = sys_get_temp_dir() . '/marko-integration/' . bin2hex(random_bytes(8));

    copyIntegrationDirectory($fixturePath ?? integrationFixturePath(), $project);

    foreach ($modules as $module) {
        $target = "$root/packages/$module";

        if (!is_dir($target)) {
            throw new RuntimeException("The module list names '$module' but $target does not exist.");
        }

        makeIntegrationDirectory("$project/vendor/marko");
        symlink($target, "$project/vendor/marko/$module");
    }

    // Class autoloading is borrowed from the monorepo; module discovery still
    // runs against this project's own vendor/marko/ links.
    file_put_contents(
        "$project/vendor/autoload.php",
        "<?php\n\ndeclare(strict_types=1);\n\nreturn require " . var_export("$root/vendor/autoload.php", true) . ";\n",
    );

    makeIntegrationDirectory("$project/storage");

    return $project;
}

/**
 * @throws RuntimeException
 */
function copyIntegrationDirectory(
    string $source,
    string $destination,
): void {
    makeIntegrationDirectory($destination);

    foreach (scandir($source) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $from = "$source/$item";
        $to = "$destination/$item";

        if (is_dir($from)) {
            copyIntegrationDirectory($from, $to);
        } elseif (!copy($from, $to)) {
            throw new RuntimeException("Could not copy fixture file $from to $to.");
        }
    }
}

/**
 * @throws RuntimeException
 */
function makeIntegrationDirectory(
    string $path,
): void {
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException("Could not create directory $path.");
    }
}

/**
 * Delete a project built by buildIntegrationProject(). Symlinks are
 * unlinked, never followed, so the monorepo packages are untouched.
 */
function removeIntegrationProject(
    string $path,
): void {
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item !== '.' && $item !== '..') {
            removeIntegrationProject("$path/$item");
        }
    }

    rmdir($path);
}

/**
 * Boot the fixture through Application::boot() against a freshly reset
 * database, then apply its migrations with the real `db:migrate` command
 * unless $migrate is false. Callers skip first via
 * integrationServicesSkipReason().
 *
 * `--no-generate` keeps db:migrate to the committed migrations, so every case
 * starts from the same schema whatever APP_ENV the developer's shell exports.
 * MigrationSafetyTest covers generation itself.
 *
 * @throws Throwable
 */
function bootIntegrationApp(
    string $project,
    bool $migrate = true,
): Application {
    resetIntegrationDatabase();

    $app = Application::boot($project);

    // marko/errors-simple's boot callback installs global error and exception
    // handlers. Hand them back to PHPUnit so a failing assertion is reported
    // as a test failure rather than rendered as an error page.
    restore_error_handler();
    restore_exception_handler();

    if ($migrate) {
        $result = runIntegrationCommand($app, 'db:migrate', ['--no-generate']);

        if ($result['exitCode'] !== 0) {
            throw new RuntimeException("db:migrate failed while booting the fixture:\n" . $result['output']);
        }
    }

    return $app;
}

/**
 * Shared beforeEach for integration-services files: skip with the reason
 * when the services are unusable, otherwise build and boot a fresh fixture
 * project and expose it as $test->project and $test->app.
 *
 * @throws Throwable
 */
function setUpIntegrationTest(
    TestCase $test,
    bool $migrate = true,
): void {
    $reason = integrationServicesSkipReason();

    if ($reason !== null) {
        $test->markTestSkipped($reason);
    }

    $test->project = buildIntegrationProject();
    $test->app = bootIntegrationApp($test->project, $migrate);
}

function tearDownIntegrationTest(
    TestCase $test,
): void {
    if (isset($test->project)) {
        removeIntegrationProject($test->project);
    }
}

/**
 * The value a response sets for a cookie, read from its Set-Cookie line.
 */
function integrationCookieValue(
    Response $response,
    string $name,
): ?string {
    foreach ($response->cookies() as $cookie) {
        if ($cookie->name() === $name) {
            $pair = explode(';', $cookie->toSetCookieString(), 2)[0];

            return urldecode(substr($pair, strlen($name) + 1));
        }
    }

    return null;
}

/**
 * Build a request the way a PHP SAPI would describe it.
 *
 * @param array<string, string> $cookies
 * @param array<string, string> $server
 * @param array<string, mixed> $post Form fields, as PHP would put them in $_POST
 */
function integrationRequest(
    string $method,
    string $uri,
    array $cookies = [],
    array $server = [],
    array $post = [],
): Request {
    return new Request(
        server: [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
            ...$server,
        ],
        post: $post,
        cookies: $cookies,
    );
}

/**
 * The Input a shell invocation `marko <command> <arguments...>` produces.
 * Input::getArguments() drops the script name and the command, so both
 * must lead the list or the first two arguments would be lost.
 *
 * @param list<string> $arguments
 */
function integrationCommandInput(
    string $command,
    array $arguments = [],
): Input {
    return new Input(['marko', $command, ...$arguments]);
}

/**
 * Run $callback with APP_ENV set to $name and MARKO_ENV cleared, restoring
 * both afterwards. AppEnvironment reads the variables on every call, so this
 * works on an app that is already booted.
 *
 * @template T
 * @param Closure(): T $callback
 * @return T
 */
function withIntegrationAppEnv(
    string $name,
    Closure $callback,
): mixed {
    $saved = [];

    foreach (['APP_ENV', 'MARKO_ENV'] as $variable) {
        $saved[$variable] = [
            'env' => array_key_exists($variable, $_ENV) ? $_ENV[$variable] : null,
            'process' => getenv($variable),
        ];
        unset($_ENV[$variable]);
        putenv($variable);
    }

    $_ENV['APP_ENV'] = $name;
    putenv("APP_ENV=$name");

    try {
        return $callback();
    } finally {
        foreach ($saved as $variable => $values) {
            unset($_ENV[$variable]);
            putenv($variable);

            if ($values['env'] !== null) {
                $_ENV[$variable] = $values['env'];
            }

            if ($values['process'] !== false) {
                putenv("$variable={$values['process']}");
            }
        }
    }
}

/**
 * Run a console command through the application's real CommandRunner.
 *
 * @param list<string> $arguments
 * @return array{exitCode: int, output: string}
 * @throws Throwable
 */
function runIntegrationCommand(
    Application $app,
    string $command,
    array $arguments = [],
): array {
    $stream = fopen('php://memory', 'w+');

    if ($stream === false) {
        throw new RuntimeException('Could not open an in-memory output stream.');
    }

    $exitCode = $app->commandRunner->run($command, integrationCommandInput($command, $arguments), new Output($stream));
    rewind($stream);
    $output = (string) stream_get_contents($stream);
    fclose($stream);

    return ['exitCode' => $exitCode, 'output' => $output];
}

/**
 * Packages the database-testing fixture needs: the database layer, the
 * Postgres driver and routing for the TestClient requests.
 */
const DATABASE_TESTING_MODULES = [
    'clock',
    'config',
    'core',
    'database',
    'database-pgsql',
    'routing',
];

/**
 * The database the database-testing fixture uses: separate from the main
 * fixture's, which is dropped before every integration test, because
 * TestDatabase keeps one connection open for the whole process.
 *
 * @param array<string, string> $env
 */
function databaseTestingDatabaseName(
    array $env,
): string {
    return integrationDatabaseName($env) . '_dbtesting';
}

/**
 * The database-testing fixture project for this process: built, and its
 * database created empty, on the first call, then reused so TestDatabase can
 * boot and migrate it once. Removed when the process ends. Callers skip
 * first via integrationServicesSkipReason().
 *
 * @throws RuntimeException|PDOException
 */
function databaseTestingProject(): string
{
    static $project = null;

    if ($project === null) {
        resetIntegrationDatabase(databaseTestingDatabaseName(getenv()));
        $project = buildIntegrationProject(__DIR__ . '/DatabaseTestingFixture', DATABASE_TESTING_MODULES);
        register_shutdown_function(static fn () => removeIntegrationProject($project));
    }

    return $project;
}

/**
 * Packages the queue/session tables fixture needs besides its database
 * driver: marko/queue-database and marko/session-database with what they
 * require. The driver (database-pgsql or database-mysql) is added per run.
 */
const QUEUE_SESSION_MODULES = [
    'clock',
    'config',
    'core',
    'database',
    'encryption',
    'encryption-openssl',
    'queue',
    'queue-database',
    'routing',
    'session',
    'session-database',
];

/**
 * Build the queue/session tables fixture (#337) for an empty database: the
 * packages, the driver named by $databaseConfig['driver'], and a
 * config/database.php written from $databaseConfig. Nothing is migrated.
 *
 * @param array<string, int|string> $databaseConfig
 * @throws RuntimeException
 */
function buildQueueSessionProject(
    array $databaseConfig,
): string {
    $project = buildIntegrationProject(
        __DIR__ . '/QueueSessionFixture',
        [...QUEUE_SESSION_MODULES, 'database-' . $databaseConfig['driver']],
    );

    file_put_contents(
        "$project/config/database.php",
        "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($databaseConfig, true) . ";\n",
    );

    return $project;
}

/**
 * The queue/session tables fixture's PostgreSQL database, created empty: its
 * own database on the fixture app's server (DB_*), so the main fixture's
 * per-test reset never touches it.
 *
 * @return array<string, int|string>
 * @throws PDOException
 */
function freshQueueSessionPgSqlDatabase(): array
{
    $env = getenv();
    $database = integrationDatabaseName($env) . '_tables';
    resetIntegrationDatabase($database);

    return [
        'driver' => 'pgsql',
        'host' => $env['DB_HOST'],
        'port' => (int) ($env['DB_PORT'] ?? 5432),
        'database' => $database,
        'username' => $env['DB_USERNAME'] ?? 'marko',
        'password' => $env['DB_PASSWORD'] ?? 'marko',
    ];
}

/**
 * The queue/session tables fixture's MySQL or MariaDB database, created
 * empty on the driver tests' server (MARKO_TEST_MYSQL_*). It is named after
 * their database with a `_tables` suffix and the parallel worker's
 * TEST_TOKEN, so it never shares tables with them.
 *
 * @return array<string, int|string>
 * @throws Throwable
 */
function freshQueueSessionMySqlDatabase(
    DatabaseConfig $server,
): array {
    $token = (string) preg_replace('/\W/', '', getenv('TEST_TOKEN') ?: '');
    $database = $server->database . '_tables' . ($token !== '' ? "_$token" : '');
    $connection = new MySqlConnection($server);

    try {
        $connection->execute("DROP DATABASE IF EXISTS `$database`");
        $connection->execute("CREATE DATABASE `$database`");
    } finally {
        $connection->disconnect();
    }

    return [
        'driver' => 'mysql',
        'host' => $server->host,
        'port' => $server->port,
        'database' => $database,
        'username' => $server->username,
        'password' => $server->password,
    ];
}

/**
 * Boot the queue/session tables fixture for $databaseConfig, exposing it as
 * $test->project and $test->app. Nothing is migrated.
 *
 * @param array<string, int|string> $databaseConfig
 * @throws Throwable
 */
function setUpQueueSessionTest(
    TestCase $test,
    array $databaseConfig,
): void {
    $test->project = buildQueueSessionProject($databaseConfig);
    $test->app = Application::boot($test->project);
}

/**
 * Run `db:migrate` the way a developer does, in the local environment, where
 * it generates a migration for the entity tables and applies it.
 *
 * @return array{exitCode: int, output: string}
 * @throws Throwable
 */
function migrateQueueSessionProject(
    Application $app,
): array {
    return withIntegrationAppEnv('local', fn (): array => runIntegrationCommand($app, 'db:migrate'));
}

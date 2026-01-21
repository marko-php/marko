<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Command\SeedCommand;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Seed\SeederDefinition;
use Marko\Database\Seed\SeederDiscovery;
use Marko\Database\Seed\SeederInterface;
use Marko\Database\Seed\SeederRunner;

/**
 * Helper to create a stub SeederDiscovery.
 *
 * @param array<SeederDefinition> $vendorDefinitions
 * @param array<SeederDefinition> $modulesDefinitions
 * @param array<SeederDefinition> $appDefinitions
 */
function createStubDiscovery(
    array $vendorDefinitions = [],
    array $modulesDefinitions = [],
    array $appDefinitions = [],
): SeederDiscovery {
    return new class ($vendorDefinitions, $modulesDefinitions, $appDefinitions) extends SeederDiscovery
    {
        public function __construct(
            private array $vendorDefs,
            private array $modulesDefs,
            private array $appDefs,
        ) {}

        public function discoverInVendor(
            string $vendorPath,
        ): array {
            return $this->vendorDefs;
        }

        public function discoverInModules(
            string $modulesPath,
        ): array {
            return $this->modulesDefs;
        }

        public function discoverInApp(
            string $appPath,
        ): array {
            return $this->appDefs;
        }
    };
}

/**
 * Helper to create a stub ConnectionInterface.
 */
function createStubConnection(): ConnectionInterface
{
    return new class () implements ConnectionInterface
    {
        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 0;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            return new class () implements StatementInterface
            {
                public function execute(
                    array $bindings = [],
                ): bool {
                    return true;
                }

                public function fetchAll(): array
                {
                    return [];
                }

                public function fetch(): ?array
                {
                    return null;
                }

                public function rowCount(): int
                {
                    return 0;
                }
            };
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };
}

/**
 * Helper to capture output.
 *
 * @return array{stream: resource, output: Output}
 */
function createOutputStream(): array
{
    $stream = fopen('php://memory', 'r+');

    return [
        'stream' => $stream,
        'output' => new Output($stream),
    ];
}

/**
 * Helper to get output content.
 *
 * @param resource $stream
 */
function getOutputContent(
    mixed $stream,
): string {
    rewind($stream);

    return stream_get_contents($stream);
}

it('registers as db:seed command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(SeedCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('db:seed');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(SeedCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('discovers all seeders from modules', function (): void {
    $seeder = new class () implements SeederInterface
    {
        public function run(
            ConnectionInterface $connection,
        ): void {}
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
        new SeederDefinition(seederClass: get_class($seeder), name: 'posts', order: 20),
    ];

    $discovery = createStubDiscovery(vendorDefinitions: $definitions);
    $connection = createStubConnection();

    $runner = new SeederRunner(
        seeders: [get_class($seeder) => $seeder],
        isProduction: false,
    );

    $command = new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:seed']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('users')
        ->and($result)->toContain('posts');
});

it('runs seeders in specified order', function (): void {
    $executionOrder = [];

    $seeder1 = new class ($executionOrder) implements SeederInterface
    {
        public function __construct(
            private array &$order,
        ) {}

        public function run(
            ConnectionInterface $connection,
        ): void {
            $this->order[] = 'second';
        }
    };

    $seeder2 = new class ($executionOrder) implements SeederInterface
    {
        public function __construct(
            private array &$order,
        ) {}

        public function run(
            ConnectionInterface $connection,
        ): void {
            $this->order[] = 'first';
        }
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder1), name: 'second', order: 20),
        new SeederDefinition(seederClass: get_class($seeder2), name: 'first', order: 10),
    ];

    $discovery = createStubDiscovery(vendorDefinitions: $definitions);
    $connection = createStubConnection();

    $runner = new SeederRunner(
        seeders: [
            get_class($seeder1) => $seeder1,
            get_class($seeder2) => $seeder2,
        ],
        isProduction: false,
    );

    $command = new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:seed']);

    $command->execute($input, $output);

    expect($executionOrder)->toBe(['first', 'second']);
});

it('shows each seeder being run', function (): void {
    $seeder = new class () implements SeederInterface
    {
        public function run(
            ConnectionInterface $connection,
        ): void {}
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
        new SeederDefinition(seederClass: get_class($seeder), name: 'posts', order: 20),
    ];

    $discovery = createStubDiscovery(vendorDefinitions: $definitions);
    $connection = createStubConnection();

    $runner = new SeederRunner(
        seeders: [get_class($seeder) => $seeder],
        isProduction: false,
    );

    $command = new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:seed']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('Running seeder: users')
        ->and($result)->toContain('Running seeder: posts');
});

it('supports --class option to run specific seeder', function (): void {
    $userSeederRan = false;
    $postSeederRan = false;

    $userSeeder = new class ($userSeederRan) implements SeederInterface
    {
        public function __construct(
            private bool &$ran,
        ) {}

        public function run(
            ConnectionInterface $connection,
        ): void {
            $this->ran = true;
        }
    };

    $postSeeder = new class ($postSeederRan) implements SeederInterface
    {
        public function __construct(
            private bool &$ran,
        ) {}

        public function run(
            ConnectionInterface $connection,
        ): void {
            $this->ran = true;
        }
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($userSeeder), name: 'users', order: 10),
        new SeederDefinition(seederClass: get_class($postSeeder), name: 'posts', order: 20),
    ];

    $discovery = createStubDiscovery(vendorDefinitions: $definitions);
    $connection = createStubConnection();

    $runner = new SeederRunner(
        seeders: [
            get_class($userSeeder) => $userSeeder,
            get_class($postSeeder) => $postSeeder,
        ],
        isProduction: false,
    );

    $command = new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:seed', '--class=users']);

    $command->execute($input, $output);

    expect($userSeederRan)->toBeTrue()
        ->and($postSeederRan)->toBeFalse();
});

it('blocks execution in production environment', function (): void {
    $seeder = new class () implements SeederInterface
    {
        public function run(
            ConnectionInterface $connection,
        ): void {}
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $discovery = createStubDiscovery(vendorDefinitions: $definitions);
    $connection = createStubConnection();

    $runner = new SeederRunner(
        seeders: [get_class($seeder) => $seeder],
        isProduction: true,
    );

    $command = new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: true,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:seed']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(1);
});

it('shows error message when blocked in production', function (): void {
    $seeder = new class () implements SeederInterface
    {
        public function run(
            ConnectionInterface $connection,
        ): void {}
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $discovery = createStubDiscovery(vendorDefinitions: $definitions);
    $connection = createStubConnection();

    $runner = new SeederRunner(
        seeders: [get_class($seeder) => $seeder],
        isProduction: true,
    );

    $command = new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: true,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:seed']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('cannot be run in production');
});

it('does NOT support --force flag (seeders never run in production)', function (): void {
    $seeder = new class () implements SeederInterface
    {
        public function run(
            ConnectionInterface $connection,
        ): void {}
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $discovery = createStubDiscovery(vendorDefinitions: $definitions);
    $connection = createStubConnection();

    $runner = new SeederRunner(
        seeders: [get_class($seeder) => $seeder],
        isProduction: true,
    );

    $command = new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: true,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    // Even with --force, it should still block
    $input = new Input(['marko', 'db:seed', '--force']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(1);

    $result = getOutputContent($stream);

    expect($result)->toContain('cannot be run in production');
});

it('shows "No seeders found" when none discovered', function (): void {
    $discovery = createStubDiscovery();
    $connection = createStubConnection();

    $runner = new SeederRunner(
        seeders: [],
        isProduction: false,
    );

    $command = new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:seed']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('No seeders found');
});

it('returns 0 on success, 1 on failure', function (): void {
    $seeder = new class () implements SeederInterface
    {
        public function run(
            ConnectionInterface $connection,
        ): void {}
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $discovery = createStubDiscovery(vendorDefinitions: $definitions);
    $connection = createStubConnection();

    $runner = new SeederRunner(
        seeders: [get_class($seeder) => $seeder],
        isProduction: false,
    );

    $command = new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:seed']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
});

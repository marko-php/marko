<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Database\Command\SeedCommand;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Seed\SeederDefinition;
use Marko\Database\Seed\SeederDiscovery;
use Marko\Database\Seed\SeederInterface;
use Marko\Database\Seed\SeederRunner;
use Marko\Database\Tests\Command\Helpers;

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
            private readonly array $vendorDefs,
            private readonly array $modulesDefs,
            private readonly array $appDefs,
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
 * Helper to create a no-op seeder for testing.
 */
function createNoOpSeeder(): SeederInterface
{
    return new class () implements SeederInterface
    {
        public function run(
            ConnectionInterface $connection,
        ): void {}
    };
}

/**
 * Helper to create a SeedCommand with standard dependencies.
 *
 * @param array<SeederDefinition> $definitions
 * @param array<string, SeederInterface> $seeders
 */
function createSeedCommand(
    array $definitions = [],
    array $seeders = [],
    bool $isProduction = false,
): SeedCommand {
    $discovery = createStubDiscovery(vendorDefinitions: $definitions);
    $connection = Helpers::createStubConnection();

    $runner = new SeederRunner(
        seeders: $seeders,
        isProduction: $isProduction,
    );

    return new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        connection: $connection,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: $isProduction,
    );
}

/**
 * Helper to execute a SeedCommand and return the output.
 *
 * @param array<string> $args
 *
 * @return array{output: string, exitCode: int}
 */
function executeSeedCommand(
    SeedCommand $command,
    array $args = ['marko', 'db:seed'],
): array {
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input($args);

    $exitCode = $command->execute($input, $output);
    $result = Helpers::getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('registers as db:seed command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(SeedCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('db:seed');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(SeedCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('discovers all seeders from modules', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
        new SeederDefinition(seederClass: get_class($seeder), name: 'posts', order: 20),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
    );

    ['output' => $output] = executeSeedCommand($command);

    expect($output)->toContain('users')
        ->and($output)->toContain('posts');
});

it('runs seeders in specified order', function (): void {
    $executionOrder = [];

    $seeder1 = new class ($executionOrder) implements SeederInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
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
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
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

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [
            get_class($seeder1) => $seeder1,
            get_class($seeder2) => $seeder2,
        ],
    );

    executeSeedCommand($command);

    expect($executionOrder)->toBe(['first', 'second']);
});

it('shows each seeder being run', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
        new SeederDefinition(seederClass: get_class($seeder), name: 'posts', order: 20),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
    );

    ['output' => $output] = executeSeedCommand($command);

    expect($output)->toContain('Running seeder: users')
        ->and($output)->toContain('Running seeder: posts');
});

it('supports --class option to run specific seeder', function (): void {
    $userSeederRan = false;
    $postSeederRan = false;

    $userSeeder = new class ($userSeederRan) implements SeederInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
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
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
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

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [
            get_class($userSeeder) => $userSeeder,
            get_class($postSeeder) => $postSeeder,
        ],
    );

    executeSeedCommand($command, ['marko', 'db:seed', '--class=users']);

    expect($userSeederRan)->toBeTrue()
        ->and($postSeederRan)->toBeFalse();
});

it('blocks execution in production environment', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
        isProduction: true,
    );

    ['exitCode' => $exitCode] = executeSeedCommand($command);

    expect($exitCode)->toBe(1);
});

it('shows error message when blocked in production', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
        isProduction: true,
    );

    ['output' => $output] = executeSeedCommand($command);

    expect($output)->toContain('cannot be run in production');
});

it('does NOT support --force flag (seeders never run in production)', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
        isProduction: true,
    );

    // Even with --force, it should still block
    ['output' => $output, 'exitCode' => $exitCode] = executeSeedCommand(
        $command,
        ['marko', 'db:seed', '--force'],
    );

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('cannot be run in production');
});

it('shows "No seeders found" when none discovered', function (): void {
    $command = createSeedCommand();

    ['output' => $output] = executeSeedCommand($command);

    expect($output)->toContain('No seeders found');
});

it('returns 0 on success, 1 on failure', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
    );

    ['exitCode' => $exitCode] = executeSeedCommand($command);

    expect($exitCode)->toBe(0);
});

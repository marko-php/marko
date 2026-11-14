<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

use Marko\Core\Environment\AppEnvironment;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\SeederException;

/**
 * Executes seeders in the correct order.
 */
readonly class SeederRunner
{
    /**
     * @param array<string, SeederInterface> $seeders Map of class names to seeder instances
     * @param AppEnvironment $appEnvironment Decides where seeders may run (see assertEnvironmentAllows())
     * @param TransactionInterface|null $transaction Optional transaction manager for atomic seeding
     */
    public function __construct(
        private array $seeders,
        private AppEnvironment $appEnvironment,
        private ?TransactionInterface $transaction = null,
    ) {}

    /**
     * Run all discovered seeders in order.
     *
     * Each seeder runs in its own transaction when a transaction manager is available.
     * If a seeder fails, its changes are rolled back but previously successful seeders remain.
     *
     * @param array<SeederDefinition> $definitions
     * @param bool $force Allow an environment that is neither development nor testing (never production)
     * @throws SeederException If the environment does not allow seeding
     */
    public function runAll(
        array $definitions,
        bool $force = false,
    ): void {
        $this->assertEnvironmentAllows($force);

        // Sort by order
        usort($definitions, fn (SeederDefinition $a, SeederDefinition $b) => $a->order <=> $b->order);

        foreach ($definitions as $definition) {
            $seeder = $this->seeders[$definition->seederClass] ?? null;

            if ($seeder === null) {
                continue;
            }

            $this->executeSeeder($seeder);
        }
    }

    /**
     * Run a specific seeder by name.
     *
     * The seeder runs in a transaction when a transaction manager is available.
     * If the seeder fails, all its changes are rolled back.
     *
     * @param array<SeederDefinition> $definitions
     * @param bool $force Allow an environment that is neither development nor testing (never production)
     * @throws SeederException If seeder not found or the environment does not allow seeding
     */
    public function runByName(
        string $name,
        array $definitions,
        bool $force = false,
    ): void {
        $this->assertEnvironmentAllows($force);

        foreach ($definitions as $definition) {
            if ($definition->name !== $name) {
                continue;
            }

            $seeder = $this->seeders[$definition->seederClass] ?? null;

            if ($seeder === null) {
                throw SeederException::seederNotFound($name);
            }

            $this->executeSeeder($seeder);

            return;
        }

        throw SeederException::seederNotFound($name);
    }

    /**
     * Seeders run freely in development and testing, never in production, and anywhere else
     * (staging, qa, ...) only when forced, the same policy db:seed applies.
     *
     * @throws SeederException
     */
    private function assertEnvironmentAllows(
        bool $force,
    ): void {
        if ($this->appEnvironment->isProduction()) {
            throw SeederException::blockedInProduction();
        }

        if (
            $this->appEnvironment->isDevelopment()
            || $this->appEnvironment->isTesting()
            || $force
        ) {
            return;
        }

        throw SeederException::requiresForce($this->appEnvironment->name());
    }

    /**
     * Execute a seeder, optionally within a transaction.
     *
     * When a transaction manager is available, the seeder runs atomically -
     * if it fails, all changes are rolled back automatically.
     */
    private function executeSeeder(
        SeederInterface $seeder,
    ): void {
        if ($this->transaction !== null) {
            $this->transaction->transaction(fn () => $seeder->run());
        } else {
            $seeder->run();
        }
    }
}

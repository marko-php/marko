<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\SeederException;

/**
 * Executes seeders in the correct order.
 */
readonly class SeederRunner
{
    /**
     * @param array<string, SeederInterface> $seeders Map of class names to seeder instances
     * @param bool $isProduction Whether we're running in production environment
     * @param TransactionInterface|null $transaction Optional transaction manager for atomic seeding
     */
    public function __construct(
        private array $seeders,
        private bool $isProduction = false,
        private ?TransactionInterface $transaction = null,
    ) {}

    /**
     * Run all discovered seeders in order.
     *
     * Each seeder runs in its own transaction when a transaction manager is available.
     * If a seeder fails, its changes are rolled back but previously successful seeders remain.
     *
     * @param array<SeederDefinition> $definitions
     * @throws SeederException If running in production environment
     */
    public function runAll(
        array $definitions,
    ): void {
        if ($this->isProduction) {
            throw SeederException::blockedInProduction();
        }

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
     * @throws SeederException If seeder not found or running in production
     */
    public function runByName(
        string $name,
        array $definitions,
    ): void {
        if ($this->isProduction) {
            throw SeederException::blockedInProduction();
        }

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

<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\SeederException;

/**
 * Executes seeders in the correct order.
 */
class SeederRunner
{
    /**
     * @param array<string, SeederInterface> $seeders Map of class names to seeder instances
     * @param bool $isProduction Whether we're running in production environment
     */
    public function __construct(
        private array $seeders,
        private bool $isProduction = false,
    ) {}

    /**
     * Run all discovered seeders in order.
     *
     * @param array<SeederDefinition> $definitions
     * @throws SeederException If running in production environment
     */
    public function runAll(
        array $definitions,
        ConnectionInterface $connection,
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

            $seeder->run($connection);
        }
    }

    /**
     * Run a specific seeder by name.
     *
     * @param array<SeederDefinition> $definitions
     * @throws SeederException If seeder not found or running in production
     */
    public function runByName(
        string $name,
        array $definitions,
        ConnectionInterface $connection,
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

            $seeder->run($connection);

            return;
        }

        throw SeederException::seederNotFound($name);
    }
}

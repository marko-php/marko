<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\ErrorOutput;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\ExpressionDefaultCanonicalizer;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Migration\DataMigrator;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Migration\Migrator;
use Marko\Database\Schema\SchemaRegistry;
use Marko\Database\Schema\Table;

/**
 * Applies pending migration files, then, in development, generates and applies a
 * migration for any difference between the entities and the database.
 *
 * Generation only runs automatically when AppEnvironment::isDevelopment() is true.
 * Elsewhere (production, staging, or APP_ENV unset) the command applies the
 * committed files and only warns about drift, unless --generate is passed.
 * A generated migration that would drop columns, indexes or foreign keys is listed
 * first and needs confirmation, or --force when nobody can answer.
 *
 * When the drift check after migrating cannot probe an expression default (the database
 * rejects it, or the user may not create a temporary table), the migrations already ran:
 * the command warns on STDERR and keeps their exit status instead of failing the deploy.
 *
 * @noinspection PhpUnused
 */
#[Command(
    name: 'db:migrate',
    description: 'Apply database migrations',
    flags: ['generate', 'no-generate', 'force', 'verbose', 'v'],
    destructive: true,
)]
readonly class MigrateCommand implements CommandInterface
{
    public function __construct(
        private Migrator $migrator,
        private DataMigrator $dataMigrator,
        private MigrationGenerator $migrationGenerator,
        private EntityDiscovery $entityDiscovery,
        private IntrospectorInterface $introspector,
        private SchemaRegistry $schemaRegistry,
        private DiffCalculator $diffCalculator,
        private SqlGeneratorInterface $sqlGenerator,
        private ProjectPaths $paths,
        private AppEnvironment $appEnvironment,
        private ConfirmationPrompterInterface $confirmationPrompter,
        private ExpressionDefaultCanonicalizer $expressionDefaultCanonicalizer,
        private ErrorOutput $errorOutput,
    ) {}

    /**
     * @throws EntityException|MigrationException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $verbose = $this->isVerbose($input);
        $noGenerate = $input->hasOption('no-generate');
        $forceGenerate = $input->hasOption('generate');

        if (
            $noGenerate
            && $forceGenerate
        ) {
            $output->writeLine('Error: --generate and --no-generate cannot be used together.');

            return 1;
        }

        $shouldGenerate = !$noGenerate
            && (
                $forceGenerate
                || $this->appEnvironment->isDevelopment()
            );

        // Get pending migrations
        $schemaPending = $this->migrator->getPending();
        $dataPending = $this->dataMigrator->getPending();

        $schemaCount = 0;
        $dataCount = 0;

        // Apply existing schema migrations first
        if (!empty($schemaPending)) {
            foreach ($schemaPending as $migration) {
                $output->writeLine("Migrating: $migration");
            }

            // Show SQL statements in verbose mode
            if ($verbose) {
                $diff = $this->calculateDiff();
                $statements = $this->sqlGenerator->generateUp($diff);

                if (!empty($statements)) {
                    $output->writeLine('');
                    $output->writeLine('SQL statements:');

                    foreach ($statements as $sql) {
                        $output->writeLine("  $sql");
                    }

                    $output->writeLine('');
                }
            }

            try {
                $applied = $this->migrator->migrate();
                $schemaCount = count($applied);

                if ($schemaCount > 0) {
                    $output->writeLine("Applied $schemaCount schema migration(s).");
                }
            } catch (MigrationException $e) {
                $output->writeLine('');
                $output->writeLine("Error: {$e->getMessage()}");

                return 1;
            }
        }

        // Apply data migrations
        if (!empty($dataPending)) {
            if ($schemaCount > 0) {
                $output->writeLine('');
            }

            foreach ($dataPending as $migration) {
                $output->writeLine("Data migrating: {$migration['name']}");
            }

            try {
                $dataApplied = $this->dataMigrator->migrate();
                $dataCount = count($dataApplied);

                if ($dataCount > 0) {
                    $output->writeLine("Applied $dataCount data migration(s).");
                }
            } catch (MigrationException $e) {
                $output->writeLine('');
                $output->writeLine("Error: {$e->getMessage()}");

                return 1;
            }
        }

        // After running existing migrations, generate a migration for any entity diff
        // (development, or --generate), or only report the drift (everywhere else).
        if ($shouldGenerate) {
            try {
                $diff = $this->calculateDiff();
                $generatedPaths = [];

                if (!$diff->isEmpty()) {
                    $exitCode = $this->confirmDestructiveChanges($diff, $input, $output);

                    if ($exitCode !== null) {
                        return $exitCode;
                    }

                    $generatedPaths = $this->generateMigrationsFromDiff($diff, $output, $verbose);
                }
            } catch (MigrationException $e) {
                $output->writeLine('');
                $output->writeLine("Error: {$e->getMessage()}");

                return 1;
            }

            // If new migrations were generated, run them
            if (!empty($generatedPaths)) {
                $newPending = $this->migrator->getPending();
                if (!empty($newPending)) {
                    foreach ($newPending as $migration) {
                        $output->writeLine("Migrating: $migration");
                    }

                    try {
                        $newApplied = $this->migrator->migrate();
                        $newCount = count($newApplied);

                        if ($newCount > 0) {
                            $output->writeLine("Applied $newCount schema migration(s).");
                            $schemaCount += $newCount;
                        }
                    } catch (MigrationException $e) {
                        $output->writeLine('');
                        $output->writeLine("Error: {$e->getMessage()}");

                        return 1;
                    }
                }
            }
        } elseif (!$noGenerate) {
            try {
                $this->reportDrift($output);
            } catch (ExpressionDefaultProbeException $e) {
                // The migrations are applied; a drift check that cannot run must not fail the deploy
                $this->warnDriftCheckSkipped($e);
            } catch (MigrationException $e) {
                $output->writeLine("Error: {$e->getMessage()}");

                return 1;
            }
        }

        // Nothing was done
        if (
            $schemaCount === 0
            && $dataCount === 0
        ) {
            $output->writeLine('Nothing to migrate.');

            return 0;
        }

        $output->writeLine('');
        $output->writeLine('Migration complete.');

        return 0;
    }

    /**
     * Outside development, list how the entities differ from the database instead of generating.
     *
     * @throws EntityException|MigrationException
     */
    private function reportDrift(
        Output $output,
    ): void {
        $diff = $this->calculateDiff();

        if ($diff->isEmpty()) {
            return;
        }

        $environment = $this->appEnvironment->name();

        $output->writeLine('Warning: Entity schema differs from database.');
        $output->writeLine("Migrations are not generated in the '$environment' environment. Differences:");

        foreach ($this->sqlGenerator->generateUp($diff) as $sql) {
            $output->writeLine("  $sql");
        }

        $output->writeLine('Run db:migrate in development to generate a migration, then commit and deploy it.');
        $output->writeLine('');
    }

    /**
     * Tell the deploy, on STDERR, that the drift check could not compare an expression default with the database,
     * so it was skipped. The migrations it follows were applied, so the command still succeeds.
     */
    private function warnDriftCheckSkipped(
        ExpressionDefaultProbeException $e,
    ): void {
        $this->errorOutput->writeLine('');
        $this->errorOutput->writeLine(
            'Warning: The drift check could not compare an expression default with the database, so it was '
            . 'skipped.',
        );
        $this->errorOutput->writeLine("  {$e->getMessage()}");
        $this->errorOutput->writeLine("  {$e->getSuggestion()}");
        $this->errorOutput->writeLine(
            '  The migrations were applied. Run db:diff once the probe works to see any drift.',
        );
        $this->errorOutput->writeLine('');
    }

    /**
     * List destructive statements and ask before generating them.
     *
     * Returns null when generation may go ahead, or the exit code to stop with:
     * 1 when nobody can confirm and --force was not passed, 0 when the user declines.
     *
     * @throws MigrationException
     */
    private function confirmDestructiveChanges(
        SchemaDiff $diff,
        Input $input,
        Output $output,
    ): ?int {
        if (!$diff->hasDestructiveChanges()) {
            return null;
        }

        $output->writeLine('This migration would remove existing database objects:');

        foreach ($this->sqlGenerator->generateUp($diff->destructiveOnly()) as $sql) {
            $output->writeLine("  $sql");
        }

        $output->writeLine('');

        if ($input->hasOption('force')) {
            return null;
        }

        if (!$this->confirmationPrompter->isInteractive()) {
            $output->writeLine('Error: Refusing to generate destructive changes without confirmation.');
            $output->writeLine('Re-run with --force to generate these changes.');
            $output->writeLine(
                'To keep a hand-made index instead, list it in #[Table(unmanagedIndexes: [...])] or '
                . 'database.migrations.ignore_indexes.',
            );

            return 1;
        }

        if ($this->confirmationPrompter->confirm('Generate a migration with these changes?')) {
            return null;
        }

        $output->writeLine('Migration generation cancelled.');

        return 0;
    }

    /**
     * Generate migration files for an entity/database diff.
     *
     * @return array<string> Paths to generated migration files
     * @throws MigrationException When a table alteration produces no SQL
     */
    private function generateMigrationsFromDiff(
        SchemaDiff $diff,
        Output $output,
        bool $verbose,
    ): array {
        $paths = $this->migrationGenerator->generate($diff);

        if (!empty($paths)) {
            foreach ($paths as $path) {
                $filename = basename($path);
                $output->writeLine("Generated: $filename");
            }

            if ($verbose) {
                $statements = $this->sqlGenerator->generateUp($diff);

                if (!empty($statements)) {
                    $output->writeLine('');
                    $output->writeLine('SQL statements:');

                    foreach ($statements as $sql) {
                        $output->writeLine("  $sql");
                    }
                }
            }

            $output->writeLine('');
        }

        return $paths;
    }

    /**
     * Calculate the diff between entities and database.
     *
     * @throws EntityException|MigrationException When the database rejects an entity's default expression
     */
    private function calculateDiff(): SchemaDiff
    {
        // Discover entities
        $entityClasses = array_merge(
            $this->entityDiscovery->discoverInVendor($this->paths->vendor),
            $this->entityDiscovery->discoverInModules($this->paths->modules),
            $this->entityDiscovery->discoverInApp($this->paths->app),
        );

        // Build entity schema
        $entitySchema = $this->buildEntitySchema($entityClasses);

        // Get database schema
        $databaseSchema = $this->getDatabaseSchema();

        // Settle expression defaults the database stores in its own spelling, then calculate the diff
        return $this->diffCalculator->calculate(
            $this->expressionDefaultCanonicalizer->canonicalize($entitySchema, $databaseSchema),
            $databaseSchema,
        );
    }

    /**
     * Build schema from entity classes.
     *
     * @param array<class-string> $entityClasses
     * @return array<string, Table>
     * @throws EntityException
     */
    private function buildEntitySchema(
        array $entityClasses,
    ): array {
        $this->schemaRegistry->clear();
        $this->schemaRegistry->registerEntities($entityClasses);

        return $this->schemaRegistry->getTables();
    }

    /**
     * Framework tables to exclude from diff (not entity-managed).
     */
    private const array EXCLUDED_TABLES = [
        'migrations',
    ];

    /**
     * Get current database schema.
     *
     * @return array<string, Table>
     */
    private function getDatabaseSchema(): array
    {
        $schema = [];

        foreach ($this->introspector->getTables() as $tableName) {
            // Skip framework tables that aren't entity-managed
            if (in_array($tableName, self::EXCLUDED_TABLES, true)) {
                continue;
            }

            $table = $this->introspector->getTable($tableName);
            if ($table !== null) {
                $schema[$tableName] = $table;
            }
        }

        return $schema;
    }

    /**
     * Check if verbose flag is set.
     */
    private function isVerbose(
        Input $input,
    ): bool {
        return $input->hasOption('verbose')
            || $input->hasOption('v');
    }
}

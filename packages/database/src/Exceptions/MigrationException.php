<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Exceptions\UnsupportedFeature;
use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use Throwable;

/**
 * Exception thrown for migration-related errors.
 */
class MigrationException extends MarkoException
{
    /**
     * The original exception is kept as previous, and its suggestion replaces the generic one when it has one.
     */
    public static function migrationFailed(
        string $migrationName,
        string $error,
        ?Throwable $previous = null,
    ): self {
        $originalSuggestion = $previous instanceof MarkoException || $previous instanceof UnsupportedFeatureExceptionInterface
            ? $previous->getSuggestion()
            : '';

        return new self(
            message: "Migration '$migrationName' failed: $error",
            context: "While running migration '$migrationName'",
            suggestion: $originalSuggestion !== ''
                ? $originalSuggestion
                : 'Fix the migration error and try again. You may need to manually revert partial changes.',
            previous: $previous,
        );
    }

    public static function partialIndexNotSupported(
        string $indexName,
        string $driver,
    ): UnsupportedMigrationFeatureException {
        return new UnsupportedMigrationFeatureException(new UnsupportedFeature(
            feature: 'partial indexes',
            driver: $driver,
            message: "Index '$indexName' declares a WHERE predicate, but $driver does not support partial indexes",
            context: "While generating SQL for index '$indexName'",
            suggestion: "Remove 'where' from the #[Index] attribute, or create the index by hand in a migration and "
                . 'list its name in #[Table(unmanagedIndexes: [...])] so db:migrate leaves it alone.',
        ));
    }

    public static function missingPreviousColumn(
        string $table,
        string $column,
    ): self {
        return new self(
            message: "Column '$table.$column' is modified, but the diff holds no previous definition for it",
            context: "While generating SQL to modify column '$table.$column'",
            suggestion: 'Build the TableDiff with columnsToModifyFrom holding the database\'s current definition of '
                . 'every column in columnsToModify (DiffCalculator does this for you).',
        );
    }

    public static function controlCharacterInIdentifier(
        string $identifier,
    ): self {
        $visible = addcslashes($identifier, "\0..\37\177\\");

        return new self(
            message: "Identifier '$visible' contains a control character",
            context: 'While generating a migration from the schema diff',
            suggestion: 'Rename the table, column, index or constraint in the database so its name holds no control '
                . 'characters (newlines, tabs, NUL), then run the migration generator again.',
        );
    }

    public static function emptyDefaultExpression(): self
    {
        return new self(
            message: 'A default expression cannot be empty',
            context: 'While creating a column default Expression',
            suggestion: "Pass the SQL of the default, such as new Expression('gen_random_uuid()'), or remove the "
                . 'default.',
        );
    }

    public static function nothingToModify(
        string $table,
        string $column,
        string $driver,
    ): self {
        return new self(
            message: "Column '$table.$column' has no type, nullability or default change for $driver to apply",
            context: "While generating SQL to modify column '$table.$column'",
            suggestion: 'Only call generateModifyColumn() when the type, nullability or default differs. A uniqueness '
                . 'change is applied through the index diff.',
        );
    }

    /**
     * @param list<string> $changes The changes the diff reported, such as `Modify column: title`
     */
    public static function emptyAlterMigration(
        string $table,
        array $changes,
    ): self {
        $changeList = implode('; ', $changes);

        return new self(
            message: "The schema diff reports changes to table '$table' ($changeList), but the SQL generator "
                . 'produced no statements for them',
            context: "While generating the alter migration for table '$table'",
            suggestion: 'The diff and the SQL generator disagree about this table, so no migration was written. Compare '
                . "the entity definition with the database's table definition and report the mismatch; until it is "
                . 'fixed, write any real change to this table by hand in a migration.',
        );
    }

    public static function rebuildNeedsCurrentTable(
        string $table,
        string $driver,
    ): self {
        return new self(
            message: "Cannot rebuild table '$table' on $driver: the table diff does not carry the current table",
            context: "While generating SQL to rebuild table '$table'",
            suggestion: 'Build the diff with DiffCalculator, which records the database\'s current table on every '
                . 'table diff.',
        );
    }

    public static function columnChangeNotSupported(
        string $table,
        string $column,
        string $driver,
        string $change,
    ): UnsupportedMigrationFeatureException {
        return new UnsupportedMigrationFeatureException(new UnsupportedFeature(
            feature: 'in-place column changes',
            driver: $driver,
            message: "Cannot change the $change of column '$table.$column' in place on $driver",
            context: "While generating SQL to modify column '$table.$column'",
            suggestion: 'Write this change by hand in a migration (for example, add a new column, copy the data and '
                . 'drop the old one), then run db:migrate again.',
        ));
    }

    /**
     * @param list<string> $existingColumns The columns of the table's current primary key
     * @param list<string> $newColumns The added columns the entity declares as primary key
     */
    public static function primaryKeyAlreadyExists(
        string $table,
        array $existingColumns,
        array $newColumns,
    ): self {
        $quote = static fn (array $columns): string => "'" . implode("', '", $columns) . "'";
        $noun = count($newColumns) === 1 ? 'column' : 'columns';

        return new self(
            message: "Cannot add primary key $noun {$quote($newColumns)} to table '$table', which already has a primary "
                . "key on {$quote($existingColumns)}",
            context: "While generating SQL to add columns to table '$table'",
            suggestion: 'A table has one primary key, and the diff does not replace it. Write this change by hand in a '
                . 'migration (drop the current primary key, add the new columns and their key), then run db:migrate '
                . 'again.',
        );
    }

    public static function migrationNotFound(
        string $migrationName,
    ): self {
        return new self(
            message: "Migration file for '$migrationName' not found",
            context: "While looking for migration '$migrationName'",
            suggestion: 'Ensure the migration file exists in the database/migrations/ directory.',
        );
    }

    public static function invalidMigrationName(
        string $migrationName,
    ): self {
        return new self(
            message: "Invalid migration name '$migrationName': names must not contain '/', '\\', '..' or NUL bytes",
            context: 'While resolving a migration name read from the migrations table',
            suggestion: 'Migration names are plain file names in database/migrations/. Remove or correct the row in '
                . 'the migrations table; a name like this suggests the table was tampered with.',
        );
    }

    public static function invalidMigration(
        string $migrationName,
    ): self {
        return new self(
            message: "Migration '$migrationName' is invalid",
            context: "While loading migration '$migrationName'",
            suggestion: 'Migration files must return an instance of Migration class.',
        );
    }

    public static function unsupportedIndexType(
        string $indexName,
        string $type,
        string $driver,
    ): UnsupportedMigrationFeatureException {
        return new UnsupportedMigrationFeatureException(new UnsupportedFeature(
            feature: 'this index type',
            driver: $driver,
            message: "Index '$indexName' is a $type index, which $driver does not support",
            context: "While generating SQL for index '$indexName'",
            suggestion: "Remove the $type index from the entity, or create it by hand in a migration and list its "
                . 'name in #[Table(unmanagedIndexes: [...])] so db:migrate leaves it alone.',
        ));
    }

    public static function foreignKeyChangeRequiresRebuild(
        string $table,
        string $foreignKey,
        string $driver,
    ): UnsupportedMigrationFeatureException {
        return new UnsupportedMigrationFeatureException(new UnsupportedFeature(
            feature: 'in-place foreign key changes',
            driver: $driver,
            message: "$driver cannot add or drop foreign key '$foreignKey' on existing table '$table' in place; "
                . 'the change needs a table rebuild',
            context: "While generating SQL for foreign key '$foreignKey' on '$table'",
            suggestion: 'This change runs as a table rebuild through generateUp(); do not call the single-statement '
                . 'foreign key methods for it.',
        ));
    }

    public static function unsupportedDefaultExpression(
        string $table,
        string $column,
        string $expression,
        string $driver,
    ): UnsupportedMigrationFeatureException {
        return new UnsupportedMigrationFeatureException(new UnsupportedFeature(
            feature: 'this default expression',
            driver: $driver,
            message: "Column '$table.$column' has the default expression '$expression', which $driver does not support",
            context: "While generating the default for column '$table.$column'",
            suggestion: "Use CURRENT_TIMESTAMP, CURRENT_DATE or CURRENT_TIME, or a parenthesised expression $driver "
                . 'understands.',
        ));
    }

    public static function nonUtcCurrentTimeDefault(
        string $table,
        string $column,
        string $expression,
        string $timezone,
        string $driver,
    ): UnsupportedMigrationFeatureException {
        return new UnsupportedMigrationFeatureException(new UnsupportedFeature(
            feature: 'non-UTC current-time defaults',
            driver: $driver,
            message: "Column '$table.$column' defaults to $expression, which $driver always evaluates in UTC, "
                . "but database.timezone is '$timezone'",
            context: "While generating the default for column '$table.$column'",
            suggestion: 'Set database.timezone to UTC, or set the value from application code instead of a column '
                . 'default.',
        ));
    }
}

<?php

declare(strict_types=1);

use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use Marko\Database\Exceptions\MigrationException;

/**
 * @return array<string, array{0: Closure(): MigrationException, 1: string, 2: string, 3: string, 4: string}>
 */
function migrationCapabilityCases(): array
{
    return [
        'partialIndexNotSupported' => [
            fn (): MigrationException => MigrationException::partialIndexNotSupported('idx_active', 'MySQL'),
            "Index 'idx_active' declares a WHERE predicate, but MySQL does not support partial indexes",
            "While generating SQL for index 'idx_active'",
            "Remove 'where' from the #[Index] attribute",
            'MySQL',
        ],
        'columnChangeNotSupported' => [
            fn (): MigrationException => MigrationException::columnChangeNotSupported('posts', 'id', 'SQLite', 'type'),
            "Cannot change the type of column 'posts.id' in place on SQLite",
            "While generating SQL to modify column 'posts.id'",
            'Write this change by hand in a migration',
            'SQLite',
        ],
        'unsupportedIndexType' => [
            fn (): MigrationException => MigrationException::unsupportedIndexType('idx_body', 'fulltext', 'SQLite'),
            "Index 'idx_body' is a fulltext index, which SQLite does not support",
            "While generating SQL for index 'idx_body'",
            'Remove the fulltext index from the entity',
            'SQLite',
        ],
        'unsupportedDefaultExpression' => [
            fn (): MigrationException => MigrationException::unsupportedDefaultExpression(
                'posts',
                'uid',
                'uuid()',
                'SQLite',
            ),
            "Column 'posts.uid' has the default expression 'uuid()', which SQLite does not support",
            "While generating the default for column 'posts.uid'",
            'Use CURRENT_TIMESTAMP, CURRENT_DATE or CURRENT_TIME',
            'SQLite',
        ],
        'foreignKeyChangeRequiresRebuild' => [
            fn (): MigrationException => MigrationException::foreignKeyChangeRequiresRebuild(
                'posts',
                'fk_user',
                'SQLite',
            ),
            "SQLite cannot add or drop foreign key 'fk_user' on existing table 'posts' in place; the change needs a table rebuild",
            "While generating SQL for foreign key 'fk_user' on 'posts'",
            'This change runs as a table rebuild through generateUp()',
            'SQLite',
        ],
        'nonUtcCurrentTimeDefault' => [
            fn (): MigrationException => MigrationException::nonUtcCurrentTimeDefault(
                'posts',
                'created_at',
                'CURRENT_TIMESTAMP',
                'Europe/Paris',
                'SQLite',
            ),
            "Column 'posts.created_at' defaults to CURRENT_TIMESTAMP, which SQLite always evaluates in UTC, but database.timezone is 'Europe/Paris'",
            "While generating the default for column 'posts.created_at'",
            'Set database.timezone to UTC',
            'SQLite',
        ],
    ];
}

describe('UnsupportedMigrationFeatureException', function (): void {
    it(
        'makes every migration capability factory return an exception that is a MigrationException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            foreach (migrationCapabilityCases() as $name => [$factory]) {
                expect($factory())
                    ->toBeInstanceOf(MigrationException::class, $name)
                    ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class, $name);
            }
        },
    );

    it('keeps the existing migration exception messages, contexts and suggestions', function (): void {
        foreach (migrationCapabilityCases() as [$factory, $message, $context, $suggestion]) {
            $exception = $factory();

            expect($exception->getMessage())->toBe($message)
                ->and($exception->getContext())->toBe($context)
                ->and($exception->getSuggestion())->toContain($suggestion);
        }
    });

    it('reports the feature and driver of a migration capability exception', function (): void {
        foreach (migrationCapabilityCases() as [$factory, , , , $driver]) {
            $exception = $factory();

            expect($exception)->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class)
                ->and($exception->getDriver())->toBe($driver)
                ->and($exception->getFeature())->not->toBeEmpty();
        }
    });
});

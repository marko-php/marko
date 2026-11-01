<?php

declare(strict_types=1);

namespace Marko\Authentication\Database\Tests\Fixtures;

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Database\Entity\PasswordResetToken;
use Marko\Authentication\Database\Repository\DatabasePasswordResetTokenRepository;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\ConfigurationException;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Schema\Table;
use Marko\Testing\Fake\FakeConfigRepository;
use ReflectionException;

/**
 * Builds the password_reset_tokens table on a real server the way db:migrate does (from the PasswordResetToken
 * entity, through the driver's generator), and repositories for a given reset provider.
 */
class PasswordResetTokenTable
{
    public const string TABLE = 'password_reset_tokens';

    public static function drop(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('DROP TABLE IF EXISTS ' . $connection->quoteIdentifier(self::TABLE));
    }

    /**
     * @throws EntityException
     */
    public static function create(
        ConnectionInterface $connection,
        SqlGeneratorInterface $generator,
    ): void {
        $diff = new SchemaDiff(tablesToCreate: [self::TABLE => self::entityTable()]);

        foreach ($generator->generateUp($diff) as $sql) {
            $connection->execute($sql);
        }
    }

    /**
     * @throws EntityException
     */
    public static function diff(
        IntrospectorInterface $introspector,
    ): SchemaDiff {
        $databaseTable = $introspector->getTable(self::TABLE);

        return new DiffCalculator()->calculate(
            [self::TABLE => self::entityTable()],
            $databaseTable !== null ? [self::TABLE => $databaseTable] : [],
        );
    }

    /**
     * @throws ConfigurationException|ReflectionException
     */
    public static function repository(
        ConnectionInterface $connection,
        QueryBuilderFactoryInterface $queryBuilderFactory,
        string $provider = 'users',
        string $timezone = 'UTC',
    ): DatabasePasswordResetTokenRepository {
        return new DatabasePasswordResetTokenRepository(
            connection: $connection,
            queryBuilderFactory: $queryBuilderFactory,
            authConfig: new AuthConfig(new FakeConfigRepository([
                'authentication.password_reset.provider' => $provider,
            ])),
            databaseTimezoneConfig: DatabaseTimezoneConfig::fromName($timezone),
        );
    }

    /**
     * @throws EntityException
     */
    public static function entityTable(): Table
    {
        return new SchemaBuilder()->build(new EntityMetadataFactory()->parse(PasswordResetToken::class));
    }
}

<?php

declare(strict_types=1);

use Marko\Database\Exceptions\CheckConstraintViolationException;
use Marko\Database\Exceptions\ConstraintViolationException;
use Marko\Database\Exceptions\DeadlockException;
use Marko\Database\Exceptions\ForeignKeyConstraintViolationException;
use Marko\Database\Exceptions\LockTimeoutException;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\NotNullConstraintViolationException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Exceptions\SerializationFailureException;
use Marko\Database\Exceptions\TransactionConflictException;
use Marko\Database\Exceptions\UniqueConstraintViolationException;
use Marko\Database\MySql\Connection\MySqlExceptionTranslator;

/**
 * Builds a PDOException shaped like the ones pdo_mysql throws: SQLSTATE in
 * errorInfo[0] and the exception code, the MySQL error number in
 * errorInfo[1], and the server message in errorInfo[2].
 */
function mysqlDriverError(
    string $sqlState,
    int $driverCode,
    string $serverMessage,
): PDOException {
    $exception = new PDOException("SQLSTATE[$sqlState]: Integrity constraint violation: $driverCode $serverMessage");
    $exception->errorInfo = [$sqlState, $driverCode, $serverMessage];
    (new ReflectionProperty(Exception::class, 'code'))->setValue($exception, $sqlState);

    return $exception;
}

describe('MySqlExceptionTranslator', function (): void {
    it('translates driver code 1062 into a unique violation with the key name', function (): void {
        $pdoException = mysqlDriverError(
            '23000',
            1062,
            "Duplicate entry 'taken@example.com' for key 'users.users_email_unique'",
        );

        $exception = new MySqlExceptionTranslator()->translate(
            $pdoException,
            'INSERT INTO `users` (`email`) VALUES (?)',
            ['taken@example.com'],
        );

        expect($exception)->toBeInstanceOf(UniqueConstraintViolationException::class)
            ->and($exception->constraintName())->toBe('users_email_unique')
            ->and($exception->table())->toBe('users')
            ->and($exception->getPrevious())->toBe($pdoException);
    });

    it('reads the table from the SQL when the key name has no table prefix', function (): void {
        $pdoException = mysqlDriverError('23000', 1062, "Duplicate entry 'x' for key 'users_email_unique'");

        $exception = new MySqlExceptionTranslator()->translate(
            $pdoException,
            'UPDATE `users` SET `email` = ? WHERE `id` = ?',
            ['x', 1],
        );

        expect($exception)->toBeInstanceOf(UniqueConstraintViolationException::class)
            ->and($exception->constraintName())->toBe('users_email_unique')
            ->and($exception->table())->toBe('users');
    });

    it('never copies the duplicate value from the driver message', function (): void {
        $pdoException = mysqlDriverError(
            '23000',
            1062,
            "Duplicate entry 'private@example.com' for key 'users.users_email_unique'",
        );

        $exception = new MySqlExceptionTranslator()->translate(
            $pdoException,
            'INSERT INTO `users` (`email`) VALUES (?)',
            ['private@example.com'],
        );

        expect($exception->getMessage())->not->toContain('private@example.com')
            ->and($exception->getContext())->not->toContain('private@example.com')
            ->and($exception->getSuggestion())->not->toContain('private@example.com');
    });

    it(
        'translates driver codes 1451 and 1452 into foreign key violations with constraint and table',
        function (): void {
            $translator = new MySqlExceptionTranslator();
            $parentRow = mysqlDriverError(
                '23000',
                1451,
                'Cannot delete or update a parent row: a foreign key constraint fails (`app`.`posts`, CONSTRAINT '
                . '`posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`))',
            );
            $childRow = mysqlDriverError(
                '23000',
                1452,
                'Cannot add or update a child row: a foreign key constraint fails (`app`.`posts`, CONSTRAINT '
                . '`posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`))',
            );

            $deleted = $translator->translate($parentRow, 'DELETE FROM `users` WHERE `id` = ?', [1]);
            $inserted = $translator->translate($childRow, 'INSERT INTO `posts` (`user_id`) VALUES (?)', [99]);

            foreach ([$deleted, $inserted] as $exception) {
                expect($exception)->toBeInstanceOf(ForeignKeyConstraintViolationException::class)
                    ->and($exception->constraintName())->toBe('posts_user_id_foreign')
                    ->and($exception->table())->toBe('posts')
                    ->and($exception->column())->toBe('user_id');
            }
        },
    );

    it('translates the legacy foreign key codes 1216 and 1217', function (): void {
        $translator = new MySqlExceptionTranslator();

        $child = $translator->translate(
            mysqlDriverError('23000', 1216, 'Cannot add or update a child row: a foreign key constraint fails'),
            'INSERT INTO posts (user_id) VALUES (?)',
            [99],
        );
        $parent = $translator->translate(
            mysqlDriverError('23000', 1217, 'Cannot delete or update a parent row: a foreign key constraint fails'),
            'DELETE FROM users WHERE id = ?',
            [1],
        );

        expect($child)->toBeInstanceOf(ForeignKeyConstraintViolationException::class)
            ->and($parent)->toBeInstanceOf(ForeignKeyConstraintViolationException::class);
    });

    it('translates driver code 1048 into a not-null violation with the column', function (): void {
        $pdoException = mysqlDriverError('23000', 1048, "Column 'email' cannot be null");

        $exception = new MySqlExceptionTranslator()->translate(
            $pdoException,
            'INSERT INTO `users` (`email`) VALUES (?)',
            [null],
        );

        expect($exception)->toBeInstanceOf(NotNullConstraintViolationException::class)
            ->and($exception->column())->toBe('email')
            ->and($exception->table())->toBe('users');
    });

    it('translates driver code 1364 into a not-null violation with the column', function (): void {
        $pdoException = mysqlDriverError('HY000', 1364, "Field 'email' doesn't have a default value");

        $exception = new MySqlExceptionTranslator()->translate(
            $pdoException,
            'INSERT INTO `users` (`name`) VALUES (?)',
            ['Ada'],
        );

        expect($exception)->toBeInstanceOf(NotNullConstraintViolationException::class)
            ->and($exception->column())->toBe('email')
            ->and($exception->table())->toBe('users');
    });

    it('translates driver code 3819 into a check violation with the constraint name', function (): void {
        $pdoException = mysqlDriverError('HY000', 3819, "Check constraint 'products_chk_1' is violated.");

        $exception = new MySqlExceptionTranslator()->translate(
            $pdoException,
            'INSERT INTO products (price) VALUES (?)',
            [-5],
        );

        expect($exception)->toBeInstanceOf(CheckConstraintViolationException::class)
            ->and($exception->constraintName())->toBe('products_chk_1')
            ->and($exception->table())->toBe('products');
    });

    it('translates the MariaDB check code 4025', function (): void {
        $pdoException = mysqlDriverError('23000', 4025, 'CONSTRAINT `products_price` failed for `app`.`products`');

        $exception = new MySqlExceptionTranslator()->translate(
            $pdoException,
            'INSERT INTO products (price) VALUES (?)',
            [-5],
        );

        expect($exception)->toBeInstanceOf(CheckConstraintViolationException::class)
            ->and($exception->constraintName())->toBe('products_price')
            ->and($exception->table())->toBe('products');
    });

    it('falls back to QueryException for any other driver code', function (): void {
        $pdoException = mysqlDriverError('42S02', 1146, "Table 'app.missing' doesn't exist");

        $exception = new MySqlExceptionTranslator()->translate($pdoException, 'SELECT * FROM missing', []);

        expect($exception)->toBeInstanceOf(QueryException::class)
            ->and($exception)->not->toBeInstanceOf(ConstraintViolationException::class)
            ->and($exception)->not->toBeInstanceOf(TransactionConflictException::class)
            ->and($exception)->not->toBeInstanceOf(LockTimeoutException::class)
            ->and($exception->sqlState())->toBe('42S02')
            ->and($exception->getPrevious())->toBe($pdoException);
    });

    it('reads the driver code from the message when errorInfo is missing', function (): void {
        $pdoException = new PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key 'users.users_email_unique'",
        );
        (new ReflectionProperty(Exception::class, 'code'))->setValue($pdoException, '23000');

        $exception = new MySqlExceptionTranslator()->translate(
            $pdoException,
            'INSERT INTO users (email) VALUES (?)',
            ['x'],
        );

        expect($exception)->toBeInstanceOf(UniqueConstraintViolationException::class)
            ->and($exception->constraintName())->toBe('users_email_unique');
    });

    it('translates error 1213 into DeadlockException', function (): void {
        $pdoException = mysqlDriverError(
            '40001',
            1213,
            'Deadlock found when trying to get lock; try restarting transaction',
        );

        $exception = new MySqlExceptionTranslator()->translate($pdoException, 'UPDATE accounts SET n = ?', [1]);

        expect($exception)->toBeInstanceOf(DeadlockException::class)
            ->and($exception->sqlState())->toBe('40001')
            ->and($exception->getPrevious())->toBe($pdoException);
    });

    it('translates error 1205 into LockTimeoutException', function (): void {
        $pdoException = mysqlDriverError('HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction');

        $exception = new MySqlExceptionTranslator()->translate($pdoException, 'UPDATE accounts SET n = ?', [1]);

        expect($exception)->toBeInstanceOf(LockTimeoutException::class)
            ->and($exception->sqlState())->toBe('HY000');
    });

    it('translates error 3572 into LockTimeoutException', function (): void {
        $pdoException = mysqlDriverError(
            'HY000',
            3572,
            'Statement aborted because lock(s) could not be acquired immediately and NOWAIT is set.',
        );

        $exception = new MySqlExceptionTranslator()->translate(
            $pdoException,
            'SELECT * FROM `jobs` WHERE `id` = ? FOR UPDATE NOWAIT',
            [1],
        );

        expect($exception)->toBeInstanceOf(LockTimeoutException::class);
    });

    it('translates error 1020 into a SerializationFailureException', function (): void {
        $pdoException = mysqlDriverError(
            'HY000',
            1020,
            "Record has changed since last read in table 'accounts'",
        );

        $exception = new MySqlExceptionTranslator()->translate($pdoException, 'UPDATE accounts SET n = ?', [1]);

        expect($exception)->toBeInstanceOf(SerializationFailureException::class)
            ->and($exception)->toBeInstanceOf(TransactionConflictException::class)
            ->and($exception->sqlState())->toBe('HY000')
            ->and($exception->getPrevious())->toBe($pdoException);
    });

    it('reads a deadlock error number from the message when errorInfo is missing', function (): void {
        $pdoException = new PDOException(
            'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; '
            . 'try restarting transaction',
        );
        (new ReflectionProperty(Exception::class, 'code'))->setValue($pdoException, '40001');

        $exception = new MySqlExceptionTranslator()->translate($pdoException, 'UPDATE accounts SET n = ?', [1]);

        expect($exception)->toBeInstanceOf(DeadlockException::class);
    });

    it(
        'translates error 2006 server has gone away to a LostConnectionException and keeps a syntax error that mentions gone away as a plain QueryException',
        function (): void {
            $lost = mysqlDriverError('HY000', 2006, 'MySQL server has gone away');
            $syntax = mysqlDriverError(
                '42000',
                1064,
                "You have an error in your SQL syntax near 'server has gone away'",
            );

            $lostException = new MySqlExceptionTranslator()->translate($lost, 'SELECT 1', []);
            $syntaxException = new MySqlExceptionTranslator()->translate($syntax, 'SELEC 1', []);

            expect($lostException)->toBeInstanceOf(LostConnectionException::class)
                ->and($lostException->getPrevious())->toBe($lost)
                ->and($syntaxException)->toBeInstanceOf(QueryException::class)
                ->not->toBeInstanceOf(LostConnectionException::class);
        },
    );

    it(
        'translates error 2013 lost connection during query to a LostConnectionException and keeps error 1317 query interrupted as a plain QueryException',
        function (): void {
            $lost = mysqlDriverError('HY000', 2013, 'Lost connection to MySQL server during query');
            $interrupted = mysqlDriverError('70100', 1317, 'Query execution was interrupted');

            $lostException = new MySqlExceptionTranslator()->translate($lost, 'SELECT SLEEP(10)', []);
            $interruptedException = new MySqlExceptionTranslator()->translate($interrupted, 'SELECT SLEEP(10)', []);

            expect($lostException)->toBeInstanceOf(LostConnectionException::class)
                ->and($interruptedException)->toBeInstanceOf(QueryException::class)
                ->not->toBeInstanceOf(LostConnectionException::class);
        },
    );

    it(
        'translates error 4031 inactivity disconnect and 1053 server shutdown to a LostConnectionException',
        function (): void {
            $inactive = mysqlDriverError(
                'HY000',
                4031,
                'The client was disconnected by the server because of inactivity',
            );
            $shutdown = mysqlDriverError('08S01', 1053, 'Server shutdown in progress');

            expect(new MySqlExceptionTranslator()->translate($inactive, 'SELECT 1', []))
                ->toBeInstanceOf(LostConnectionException::class)
                ->and(new MySqlExceptionTranslator()->translate($shutdown, 'SELECT 1', []))
                ->toBeInstanceOf(LostConnectionException::class);
        },
    );

    it('translates MariaDB error 1927 connection was killed to a LostConnectionException', function (): void {
        $killed = mysqlDriverError('70100', 1927, 'Connection was killed');

        expect(new MySqlExceptionTranslator()->translate($killed, 'SELECT 1', []))
            ->toBeInstanceOf(LostConnectionException::class);
    });

    it('translates a gone away message without a driver code to a LostConnectionException', function (): void {
        $lost = new PDOException('MySQL server has gone away');
        $lostToServer = new PDOException('Lost connection to server');
        $other = new PDOException('Something else broke');

        expect(new MySqlExceptionTranslator()->translate($lost, 'SELECT 1', []))
            ->toBeInstanceOf(LostConnectionException::class)
            ->and(new MySqlExceptionTranslator()->translate($lostToServer, 'SELECT 1', []))
            ->toBeInstanceOf(LostConnectionException::class)
            ->and(new MySqlExceptionTranslator()->translate($other, 'SELECT 1', []))
            ->not->toBeInstanceOf(LostConnectionException::class);
    });

    it('translates error 2055 lost connection at a named stage to a LostConnectionException', function (): void {
        $lost = mysqlDriverError(
            'HY000',
            2055,
            "Lost connection to MySQL server at 'reading initial communication packet'",
        );

        expect(new MySqlExceptionTranslator()->translate($lost, 'SELECT 1', []))
            ->toBeInstanceOf(LostConnectionException::class);
    });
});

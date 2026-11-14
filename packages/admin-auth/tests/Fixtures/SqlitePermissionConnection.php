<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Fixtures;

use Closure;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use PDO;
use RuntimeException;
use Throwable;

/**
 * An in-memory SQLite connection with the permissions, roles, role_permissions and admin_user_roles tables,
 * so the repository unit tests run their real SQL. admin_user_roles has the (user_id, role_id) unique index
 * of the real pivot, so a duplicate role id fails as it does on MySQL and PostgreSQL. Every statement is logged; failOn makes the first
 * execute() whose SQL contains the given text throw, to prove a rollback.
 */
class SqlitePermissionConnection implements ConnectionInterface, TransactionInterface
{
    /**
     * Statements and transaction operations, in order.
     *
     * @var list<string>
     */
    public array $log = [];

    public ?string $failOn = null;

    private PDO $pdo;

    private int $level = 0;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, "key" TEXT NOT NULL UNIQUE, '
            . 'label TEXT NOT NULL, "group" TEXT NOT NULL, created_at TEXT NULL)',
        );
        $this->pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $this->pdo->exec(
            'CREATE TABLE role_permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL)',
        );
        $this->pdo->exec(
            'CREATE TABLE admin_user_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, UNIQUE (user_id, role_id))',
        );
    }

    /**
     * @return list<int> The role ids the admin user holds, sorted
     */
    public function roleIdsForUser(
        int $userId,
    ): array {
        $statement = $this->pdo->prepare('SELECT role_id FROM admin_user_roles WHERE user_id = ? ORDER BY role_id');
        $statement->execute([$userId]);

        return array_map(intval(...), array_column($statement->fetchAll(), 'role_id'));
    }

    /**
     * @return list<int> The permission ids the role holds, sorted
     */
    public function permissionIdsForRole(
        int $roleId,
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT permission_id FROM role_permissions WHERE role_id = ? ORDER BY permission_id',
        );
        $statement->execute([$roleId]);

        return array_map(intval(...), array_column($statement->fetchAll(), 'permission_id'));
    }

    /**
     * Insert a permission row directly and return its id.
     */
    public function addPermission(
        string $key,
        string $label = 'Label',
        string $group = 'group',
    ): int {
        $this->pdo->prepare('INSERT INTO permissions ("key", label, "group") VALUES (?, ?, ?)')
            ->execute([$key, $label, $group]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Give a role (created on first use) the permission with the given key.
     */
    public function grant(
        int $roleId,
        string $key,
    ): void {
        $this->pdo->prepare('INSERT OR IGNORE INTO roles (id, name) VALUES (?, ?)')->execute(
            [$roleId, "role-$roleId"],
        );
        $this->pdo->prepare(
            'INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE "key" = ?',
        )->execute([$roleId, $key]);
    }

    /**
     * @return array<string, array{label: string, group: string}> Rows keyed by permission key
     */
    public function permissions(): array
    {
        $rows = [];

        foreach ($this->pdo->query('SELECT "key", label, "group" FROM permissions ORDER BY "key"') as $row) {
            $rows[$row['key']] = ['label' => $row['label'], 'group' => $row['group']];
        }

        return $rows;
    }

    /**
     * @return list<string> The permission keys the role holds, sorted
     */
    public function keysForRole(
        int $roleId,
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT p."key" FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'WHERE rp.role_id = ? ORDER BY p."key"',
        );
        $statement->execute([$roleId]);

        return array_column($statement->fetchAll(), 'key');
    }

    public function rolePermissionCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn();
    }

    /**
     * @return list<string> Logged statements that start with the given verb (e.g. "DELETE")
     */
    public function statements(
        string $verb,
    ): array {
        return array_values(array_filter(
            $this->log,
            fn (string $entry): bool => str_starts_with(ltrim($entry), $verb),
        ));
    }

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
        $this->log[] = $sql;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->log[] = $sql;

        if (
            $this->failOn !== null
            && str_contains($sql, $this->failOn)
        ) {
            throw new RuntimeException("Simulated failure on: $sql");
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function driverName(): string
    {
        return 'sqlite';
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function beginTransaction(): void
    {
        $this->log[] = 'BEGIN';

        if ($this->level === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT level_$this->level");
        }

        $this->level++;
    }

    public function commit(): void
    {
        $this->log[] = 'COMMIT';
        $this->level--;

        if ($this->level === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT level_$this->level");
        }
    }

    public function rollback(): void
    {
        $this->log[] = 'ROLLBACK';
        $this->level--;

        if ($this->level === 0) {
            $this->pdo->rollBack();
        } else {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT level_$this->level");
            $this->pdo->exec("RELEASE SAVEPOINT level_$this->level");
        }
    }

    public function inTransaction(): bool
    {
        return $this->level > 0;
    }

    /**
     * @throws Throwable
     */
    public function transaction(
        callable $callback,
        int $attempts = 1,
        int|Closure|null $backoff = null,
    ): mixed {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollback();

            throw $e;
        }
    }

    public function transactionLevel(): int
    {
        return $this->level;
    }

    public function afterCommit(
        callable $callback,
    ): void {
        $callback();
    }

    public function afterRollback(callable $callback): void {}
}

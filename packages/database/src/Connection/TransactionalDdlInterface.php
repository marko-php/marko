<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

/**
 * Implemented by connections whose database can undo schema changes (DDL)
 * together with data changes, so a whole migration can run inside one
 * transaction and a failure halfway leaves the schema as it was. PostgreSQL
 * does; a read/write split answers for its primary.
 *
 * A connection that does not implement this interface is treated as not
 * supporting transactional DDL, so callers check for it:
 *
 *     $transactional = $connection instanceof TransactionalDdlInterface
 *         && $connection->supportsTransactionalDdl();
 *
 * MySQL and MariaDB do not implement it: most DDL commits implicitly there, so
 * a transaction cannot protect a schema migration. SQLite does not implement
 * it either, although its DDL is transactional: the table rebuilds its
 * generator emits manage their own transaction and toggle PRAGMA foreign_keys,
 * which cannot happen inside an outer transaction (see generateRebuild() in
 * SqliteGenerator), so a migration there is not wrapped.
 */
interface TransactionalDdlInterface
{
    /**
     * Whether a schema migration can run inside one transaction on this
     * connection and be rolled back as a whole.
     */
    public function supportsTransactionalDdl(): bool;
}

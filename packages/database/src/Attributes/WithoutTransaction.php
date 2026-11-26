<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

/**
 * Marks a migration that must run outside a transaction.
 *
 * On a database with transactional DDL (PostgreSQL) every migration runs, together with the write to the
 * migrations table, in one transaction. Mark the migration class with this attribute when it contains a
 * statement PostgreSQL refuses inside a transaction block, such as CREATE INDEX CONCURRENTLY. It applies to
 * both up() and down(). A failure then leaves whatever statements already ran in place.
 *
 *     return new #[WithoutTransaction] class () extends Migration { ... };
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class WithoutTransaction {}

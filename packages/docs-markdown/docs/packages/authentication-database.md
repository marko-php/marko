---
title: marko/authentication-database
description: Database-backed password reset token storage --- reset tokens survive cache flushes and auth:clear-reset-tokens deletes expired rows.
---

Database-backed password reset token storage --- reset tokens survive cache flushes and `auth:clear-reset-tokens` deletes expired rows. Installing the package replaces `CachePasswordResetTokenRepository`, the cache default of [`marko/authentication`](/docs/packages/authentication/#password-reset), with `DatabasePasswordResetTokenRepository`, which keeps one hashed token per user in a `password_reset_tokens` table.

Implements `PasswordResetTokenRepositoryInterface` from [`marko/authentication`](/docs/packages/authentication/) and requires [`marko/database`](/docs/packages/database/) for the database connection.

## Installation

```bash
composer require marko/authentication-database
```

Requires [`marko/database`](/docs/packages/database/) and one of its drivers, such as [`marko/database-mysql`](/docs/packages/database-mysql/) or [`marko/database-pgsql`](/docs/packages/database-pgsql/).

## Configuration

The package has no config file of its own. It reads two keys of `config/authentication.php`:

| Key | Used for |
|---|---|
| `password_reset.provider` | The `provider` column of every row the repository writes, reads or deletes |
| `password_reset.expire_seconds` | How old a token may be before `auth:clear-reset-tokens` deletes it |

`created_at` is written in the database timezone, `database.timezone` in `config/database.php` (UTC by default), so a stored instant never depends on the PHP timezone of the process that wrote it.

## Usage

Installing the package is all that is required. `PasswordResetBrokerInterface` keeps working unchanged: it now stores and redeems tokens through the table instead of the cache. An app module that binds `PasswordResetTokenRepositoryInterface` itself still overrides both repositories.

### Creating the Table

The package ships a `PasswordResetToken` entity (`Marko\Authentication\Database\Entity\PasswordResetToken`) that owns the `password_reset_tokens` table schema. Create the table with [`marko db:migrate`](/docs/packages/database/):

```bash
marko db:migrate
```

In development, `db:migrate` generates a migration for the table in `database/migrations/` and applies it. Commit that migration; `db:migrate` on staging and production applies it. The table has these columns on MySQL, MariaDB and PostgreSQL:

| Column | Type | Notes |
|---|---|---|
| `id` | Integer | Auto-increment primary key |
| `provider` | `VARCHAR(100)` | The `password_reset.provider` the token was issued for |
| `user_id` | `VARCHAR(255)` | The user's auth identifier as a string, so integer, UUID and email identifiers all fit |
| `token_hash` | `VARCHAR(64)` | SHA-256 hash of the token; the token itself is never stored |
| `created_at` | `DATETIME` | When the token was issued, in the database timezone |

A unique index on `(provider, user_id)` keeps one token per user: issuing a new link replaces the old token through a single upsert (`ON DUPLICATE KEY UPDATE` on MySQL, `ON CONFLICT DO UPDATE` on PostgreSQL), so two reset requests racing for one user still leave one row. An index on `created_at` serves the cleanup.

The table stores no expiry. The broker rejects a token once it is `expire_seconds` old, measured from `created_at`, so changing `expire_seconds` applies to tokens already issued.

The entity only owns the schema: `DatabasePasswordResetTokenRepository` reads and writes the table with its own SQL. Because the table belongs to an entity, `db:diff` reports drift in it and [`TruncateDatabase`](/docs/packages/testing/#truncatedatabase) empties it between tests.

### Clearing Expired Tokens

A used token is deleted when it is redeemed, but a token nobody redeems stays in the table after it expires. Run the cleanup command on a schedule:

```bash
marko auth:clear-reset-tokens
```

It deletes every token created more than `expire_seconds` ago, of every provider, and reports how many it removed. Tokens left behind by a provider the app no longer resets passwords for are cleared too.

### Providers

Rows are keyed by provider and user id, so a token issued for user 7 of the `users` provider never matches user 7 of `admins`. If you change `password_reset.provider`, tokens issued under the old provider stop matching anyone and `auth:clear-reset-tokens` removes them once they expire.

## API Reference

### DatabasePasswordResetTokenRepository

Implements `PasswordResetTokenRepositoryInterface`. Takes a `ConnectionInterface`, a `QueryBuilderFactoryInterface` (for the driver's upsert), the `AuthConfig` and the `DatabaseTimezoneConfig`; all four are autowired.

| Method | Description |
|---|---|
| `store(int\|string $userId, string $tokenHash, DateTimeImmutable $createdAt): void` | Upsert the user's row for the configured provider, replacing any token the user already has. |
| `find(int\|string $userId): ?PasswordResetTokenRecord` | The user's token hash and creation time for the configured provider, or `null`. |
| `delete(int\|string $userId): void` | Delete the user's token for the configured provider. |
| `deleteExpired(DateTimeImmutable $before): int` | Delete every token, of every provider, created before `$before`. Returns the number of deleted rows. |

### PasswordResetToken

The entity that owns the `password_reset_tokens` schema. It is not used to read or write rows.

## Related Packages

- [marko/authentication](/docs/packages/authentication/) --- the password reset broker and the cache default this package replaces
- [marko/database](/docs/packages/database/) --- connection, entities and `db:migrate`
- [marko/session-database](/docs/packages/session-database/) --- database-backed sessions

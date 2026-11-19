<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Sql;

use Marko\Database\Query\IdentifierValidator;

/**
 * The one identifier-quoting rule for PostgreSQL.
 *
 * PgSqlGenerator, PgSqlQueryBuilder, PgSqlIntrospector and PgSqlConnection::quoteIdentifier() all quote through
 * it, so a name is quoted the same way whichever code path emits it.
 */
class PgSqlIdentifier
{
    private const string DELIMITER = '"';

    /**
     * Quote a table or column name with double quotes.
     *
     * A `table.column` name has each part quoted. An embedded double quote is doubled, so a name cannot break
     * out of its delimiters. Reserved words are safe once quoted, and mixed case is kept as written instead of
     * being folded to lower case.
     */
    public static function quote(
        string $identifier,
    ): string {
        return implode('.', array_map(
            static fn (string $part): string => self::DELIMITER
                . IdentifierValidator::escapeDelimiter($part, self::DELIMITER)
                . self::DELIMITER,
            explode('.', $identifier),
        ));
    }

    /**
     * Read back a single name as PostgreSQL prints it, the inverse of quote().
     *
     * PostgreSQL quotes a name in its own output (pg_indexes.indexdef, for one) only when it has to: a reserved
     * word, mixed case or an embedded quote. Anything not wrapped in quotes, such as a plain name or an index
     * expression, is returned unchanged.
     */
    public static function unquote(
        string $identifier,
    ): string {
        if (
            strlen($identifier) < 2
            || !str_starts_with($identifier, self::DELIMITER)
            || !str_ends_with($identifier, self::DELIMITER)
        ) {
            return $identifier;
        }

        return str_replace(self::DELIMITER . self::DELIMITER, self::DELIMITER, substr($identifier, 1, -1));
    }
}

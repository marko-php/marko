<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Sql;

use Marko\Database\Query\IdentifierValidator;

/**
 * The one identifier-quoting rule for SQLite.
 *
 * Everything in this driver that emits SQL quotes names through it, so a name is quoted the same way
 * whichever code path emits it.
 */
class SqliteIdentifier
{
    private const string DELIMITER = '"';

    /**
     * Quote a table or column name with double quotes.
     *
     * A `table.column` name has each part quoted. An embedded double quote is doubled, so a name cannot break
     * out of its delimiters.
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
}

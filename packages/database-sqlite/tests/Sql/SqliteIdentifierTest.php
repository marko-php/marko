<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Sql;

use Marko\Database\Sqlite\Sql\SqliteIdentifier;

describe('SqliteIdentifier', function (): void {
    it('quotes identifiers with double quotes and escapes embedded double quotes', function (): void {
        expect(SqliteIdentifier::quote('users'))->toBe('"users"')
            ->and(SqliteIdentifier::quote('users.email'))->toBe('"users"."email"')
            ->and(SqliteIdentifier::quote('we"ird'))->toBe('"we""ird"');
    });
});

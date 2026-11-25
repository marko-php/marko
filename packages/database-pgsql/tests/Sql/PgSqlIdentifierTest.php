<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Sql;

use Marko\Database\PgSql\Sql\PgSqlIdentifier;

describe('PgSqlIdentifier', function (): void {
    it('wraps a plain name in double quotes', function (): void {
        expect(PgSqlIdentifier::quote('users'))->toBe('"users"');
    });

    it('quotes each part of a table.column name', function (): void {
        expect(PgSqlIdentifier::quote('users.email'))->toBe('"users"."email"');
    });

    it('doubles an embedded double quote', function (): void {
        expect(PgSqlIdentifier::quote('we"ird'))->toBe('"we""ird"')
            ->and(PgSqlIdentifier::quote('a"b.c"d'))->toBe('"a""b"."c""d"');
    });

    it('quotes a reserved word', function (): void {
        expect(PgSqlIdentifier::quote('group'))->toBe('"group"')
            ->and(PgSqlIdentifier::quote('order'))->toBe('"order"')
            ->and(PgSqlIdentifier::quote('user'))->toBe('"user"');
    });

    it('preserves mixed case', function (): void {
        expect(PgSqlIdentifier::quote('createdAt'))->toBe('"createdAt"');
    });

    it('keeps backticks and dollar signs as written', function (): void {
        expect(PgSqlIdentifier::quote('a`b$$c'))->toBe('"a`b$$c"');
    });

    it('unquotes a quoted name and undoubles its embedded quotes', function (): void {
        expect(PgSqlIdentifier::unquote('"group"'))->toBe('group')
            ->and(PgSqlIdentifier::unquote('"createdAt"'))->toBe('createdAt')
            ->and(PgSqlIdentifier::unquote('"we""ird"'))->toBe('we"ird');
    });

    it('leaves a name PostgreSQL did not quote as it is', function (): void {
        expect(PgSqlIdentifier::unquote('email'))->toBe('email')
            ->and(PgSqlIdentifier::unquote('lower((email)::text)'))->toBe('lower((email)::text)');
    });

    it('unquotes what it quoted', function (string $name): void {
        expect(PgSqlIdentifier::unquote(PgSqlIdentifier::quote($name)))->toBe($name);
    })->with(['users', 'group', 'createdAt', 'we"ird', '"', 'a`b$$c']);
});

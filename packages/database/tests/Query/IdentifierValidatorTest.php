<?php

declare(strict_types=1);

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Query\IdentifierValidator;

describe('IdentifierValidator', function (): void {
    it('exposes the full comparison operator allowlist', function (): void {
        $operators = IdentifierValidator::OPERATORS;

        expect($operators)->toContain('=')
            ->and($operators)->toContain('!=')
            ->and($operators)->toContain('<>')
            ->and($operators)->toContain('<')
            ->and($operators)->toContain('>')
            ->and($operators)->toContain('<=')
            ->and($operators)->toContain('>=')
            ->and($operators)->toContain('LIKE')
            ->and($operators)->toContain('NOT LIKE')
            ->and($operators)->toContain('IN')
            ->and($operators)->toContain('NOT IN')
            ->and($operators)->toContain('IS')
            ->and($operators)->toContain('IS NOT');
    });

    it('accepts every allowlisted operator via assertValidOperator without throwing', function (): void {
        foreach (IdentifierValidator::OPERATORS as $operator) {
            expect(fn () => IdentifierValidator::assertValidOperator($operator))
                ->not->toThrow(InvalidColumnException::class);
        }
    });

    it('rejects an operator not in the allowlist (e.g. "; DROP TABLE") via assertValidOperator', function (): void {
        expect(fn () => IdentifierValidator::assertValidOperator('; DROP TABLE'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a lowercase-only keyword operator that does not match the allowlist casing', function (): void {
        expect(fn () => IdentifierValidator::assertValidOperator('like'))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => IdentifierValidator::assertValidOperator('not like'))
            ->toThrow(InvalidColumnException::class);
    });

    it(
        'rejects an allowlisted operator with surrounding whitespace or trailing SQL (e.g. "= OR 1=1")',
        function (): void {
            expect(fn () => IdentifierValidator::assertValidOperator('= OR 1=1'))
                ->toThrow(InvalidColumnException::class)
                ->and(fn () => IdentifierValidator::assertValidOperator(' = '))
                ->toThrow(InvalidColumnException::class)
                ->and(fn () => IdentifierValidator::assertValidOperator('= --'))
                ->toThrow(InvalidColumnException::class);
        },
    );

    it('accepts a plain identifier via assertValidIdentifier', function (): void {
        expect(fn () => IdentifierValidator::assertValidIdentifier('name'))
            ->not->toThrow(InvalidColumnException::class)
            ->and(fn () => IdentifierValidator::assertValidIdentifier('user_id'))
            ->not->toThrow(InvalidColumnException::class)
            ->and(fn () => IdentifierValidator::assertValidIdentifier('_private'))
            ->not->toThrow(InvalidColumnException::class);
    });

    it('accepts a qualified table.column identifier via assertValidIdentifier', function (): void {
        expect(fn () => IdentifierValidator::assertValidIdentifier('users.name'))
            ->not->toThrow(InvalidColumnException::class)
            ->and(fn () => IdentifierValidator::assertValidIdentifier('orders.created_at'))
            ->not->toThrow(InvalidColumnException::class);
    });

    it(
        'rejects an identifier containing a backtick, semicolon, or comment marker via assertValidIdentifier',
        function (): void {
            expect(fn () => IdentifierValidator::assertValidIdentifier('`name`'))
                ->toThrow(InvalidColumnException::class)
                ->and(fn () => IdentifierValidator::assertValidIdentifier('name;'))
                ->toThrow(InvalidColumnException::class)
                ->and(fn () => IdentifierValidator::assertValidIdentifier('name--'))
                ->toThrow(InvalidColumnException::class)
                ->and(fn () => IdentifierValidator::assertValidIdentifier('name/*comment*/'))
                ->toThrow(InvalidColumnException::class);
        },
    );

    it('escapes an embedded backtick by doubling it via escapeDelimiter', function (): void {
        expect(IdentifierValidator::escapeDelimiter('name`with`backtick', '`'))
            ->toBe('name``with``backtick');
    });

    it('escapes an embedded double-quote by doubling it via escapeDelimiter', function (): void {
        expect(IdentifierValidator::escapeDelimiter('name"with"quote', '"'))
            ->toBe('name""with""quote');
    });

    it('throws InvalidColumnException with a helpful suggestion when an operator is rejected', function (): void {
        $exception = null;

        try {
            IdentifierValidator::assertValidOperator('BADOP');
        } catch (InvalidColumnException $e) {
            $exception = $e;
        }

        expect($exception)->toBeInstanceOf(InvalidColumnException::class)
            ->and($exception->getMessage())->toContain('BADOP')
            ->and($exception->getSuggestion())->toContain('=');
    });

    it('parses a simple column name without alias', function (): void {
        $result = IdentifierValidator::parseSelectExpression('name');

        expect($result['column'])->toBe('name')
            ->and($result['alias'])->toBeNull();
    });

    it('parses a qualified column with table prefix (users.name)', function (): void {
        $result = IdentifierValidator::parseSelectExpression('users.name');

        expect($result['column'])->toBe('users.name')
            ->and($result['alias'])->toBeNull();
    });

    it('parses a column with an alias using \'as\' keyword (case-insensitive)', function (): void {
        $resultLower = IdentifierValidator::parseSelectExpression('name as author_name');
        $resultUpper = IdentifierValidator::parseSelectExpression('name AS author_name');
        $resultMixed = IdentifierValidator::parseSelectExpression('name As author_name');

        expect($resultLower['column'])->toBe('name')
            ->and($resultLower['alias'])->toBe('author_name')
            ->and($resultUpper['column'])->toBe('name')
            ->and($resultUpper['alias'])->toBe('author_name')
            ->and($resultMixed['column'])->toBe('name')
            ->and($resultMixed['alias'])->toBe('author_name');
    });

    it('parses an aggregate expression with an alias (COUNT(*) as total)', function (): void {
        $result = IdentifierValidator::parseSelectExpression('COUNT(*) as total');

        expect($result['column'])->toBe('COUNT(*)')
            ->and($result['alias'])->toBe('total');
    });

    it('rejects an alias containing characters outside [a-zA-Z0-9_]', function (): void {
        expect(fn () => IdentifierValidator::parseSelectExpression('name as author-name'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects an alias that starts with a digit', function (): void {
        expect(fn () => IdentifierValidator::parseSelectExpression('name as 1alias'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a column name containing a SQL comment or semicolon', function (): void {
        expect(fn () => IdentifierValidator::parseSelectExpression('name; DROP TABLE users'))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => IdentifierValidator::parseSelectExpression('name -- comment'))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => IdentifierValidator::parseSelectExpression('name /* comment */ as n'))
            ->toThrow(InvalidColumnException::class);
    });
});

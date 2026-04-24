<?php

declare(strict_types=1);

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Query\IdentifierValidator;

describe('IdentifierValidator', function (): void {
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
            ->toThrow(InvalidColumnException::class);

        expect(fn () => IdentifierValidator::parseSelectExpression('name -- comment'))
            ->toThrow(InvalidColumnException::class);

        expect(fn () => IdentifierValidator::parseSelectExpression('name /* comment */ as n'))
            ->toThrow(InvalidColumnException::class);
    });
});

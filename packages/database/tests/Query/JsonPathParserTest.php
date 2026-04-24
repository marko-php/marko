<?php

declare(strict_types=1);

use Marko\Database\Exceptions\InvalidJsonPathException;
use Marko\Database\Query\JsonPathParser;

describe('JsonPathParser', function (): void {
    it('parses a two-segment JSON path with -> (data->name)', function (): void {
        $result = JsonPathParser::parse('data->name');

        expect($result->column)->toBe('data')
            ->and($result->segments)->toBe(['name'])
            ->and($result->operator)->toBe('->');
    });

    it('parses a deeply nested JSON path with multiple -> segments (data->user->address->city)', function (): void {
        $result = JsonPathParser::parse('data->user->address->city');

        expect($result->column)->toBe('data')
            ->and($result->segments)->toBe(['user', 'address', 'city'])
            ->and($result->operator)->toBe('->');
    });

    it('rejects JSON path expressions containing semicolons or SQL comments', function (): void {
        expect(fn () => JsonPathParser::parse('data->name; DROP TABLE users'))
            ->toThrow(InvalidJsonPathException::class);

        expect(fn () => JsonPathParser::parse('data->name -- comment'))
            ->toThrow(InvalidJsonPathException::class);

        expect(fn () => JsonPathParser::parse('data->name /* comment */ '))
            ->toThrow(InvalidJsonPathException::class);
    });

    it('rejects JSON path segments that fail the identifier whitelist (no injection)', function (): void {
        expect(fn () => JsonPathParser::parse('data->user name'))
            ->toThrow(InvalidJsonPathException::class);

        expect(fn () => JsonPathParser::parse('data->1invalid'))
            ->toThrow(InvalidJsonPathException::class);

        expect(fn () => JsonPathParser::parse('data->key space->name'))
            ->toThrow(InvalidJsonPathException::class);
    });

    it('distinguishes -> (JSON-typed result) from ->> (text-typed result) in path expressions', function (): void {
        $json = JsonPathParser::parse('data->name');
        $text = JsonPathParser::parse('data->>name');

        expect($json->operator)->toBe('->')
            ->and($json->column)->toBe('data')
            ->and($json->segments)->toBe(['name'])
            ->and($text->operator)->toBe('->>')
            ->and($text->column)->toBe('data')
            ->and($text->segments)->toBe(['name']);
    });
});

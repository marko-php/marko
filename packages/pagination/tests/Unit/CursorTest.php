<?php

declare(strict_types=1);

use Marko\Pagination\Contracts\CursorInterface;
use Marko\Pagination\Cursor;
use Marko\Pagination\Exceptions\PaginationException;

it('implements CursorInterface', function () {
    $cursor = new Cursor(['id' => 42]);

    expect($cursor)->toBeInstanceOf(CursorInterface::class);
});

it('creates Cursor value object from column/value parameters', function () {
    $cursor = new Cursor(['id' => 42, 'created_at' => '2024-01-01']);

    expect($cursor->parameters())->toBe(['id' => 42, 'created_at' => '2024-01-01']);
});

it('retrieves individual parameter by name', function () {
    $cursor = new Cursor(['id' => 42, 'name' => 'test']);

    expect($cursor->parameter('id'))->toBe(42)
        ->and($cursor->parameter('name'))->toBe('test');
});

it('returns null for non-existent parameter', function () {
    $cursor = new Cursor(['id' => 42]);

    expect($cursor->parameter('missing'))->toBeNull();
});

it('encodes Cursor to URL-safe base64 string', function () {
    $cursor = new Cursor(['id' => 42]);

    $encoded = $cursor->encode();

    expect($encoded)->toBeString()
        ->and(base64_decode($encoded, true))->not->toBeFalse()
        ->and(json_decode(base64_decode($encoded), true))->toBe(['id' => 42]);
});

it('decodes base64 string back to Cursor', function () {
    $original = new Cursor(['id' => 42, 'created_at' => '2024-01-01']);
    $encoded = $original->encode();

    $decoded = Cursor::decode($encoded);

    expect($decoded)->toBeInstanceOf(Cursor::class)
        ->and($decoded->parameters())->toBe(['id' => 42, 'created_at' => '2024-01-01']);
});

it('roundtrips encode and decode correctly', function () {
    $params = ['id' => 100, 'sort' => 'desc', 'score' => 3.14];
    $cursor = new Cursor($params);

    $decoded = Cursor::decode($cursor->encode());

    expect($decoded->parameters())->toBe($params);
});

it('throws PaginationException for invalid base64 cursor string', function () {
    Cursor::decode('not-valid-base64!!!');
})->throws(PaginationException::class);

it('throws PaginationException for base64 string with invalid JSON', function () {
    $invalidJson = base64_encode('not json');

    Cursor::decode($invalidJson);
})->throws(PaginationException::class);

it('throws PaginationException for base64 string with non-array JSON', function () {
    $nonArrayJson = base64_encode('"just a string"');

    Cursor::decode($nonArrayJson);
})->throws(PaginationException::class);

it('throws PaginationException for empty cursor string', function () {
    Cursor::decode('');
})->throws(PaginationException::class);

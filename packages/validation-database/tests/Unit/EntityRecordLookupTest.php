<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\EntityException;
use Marko\Validation\Contracts\RecordLookupInterface;
use Marko\Validation\Database\EntityRecordLookup;
use Marko\Validation\Database\Exceptions\RecordLookupException;
use Marko\Validation\Database\Tests\Fixtures\ChannelStatus;
use Marko\Validation\Database\Tests\Fixtures\LookupAccount;
use Marko\Validation\Database\Tests\Fixtures\LookupChannel;
use Marko\Validation\Database\Tests\Fixtures\RecordingConnection;

beforeEach(function (): void {
    $this->connection = new RecordingConnection();
    $metadataFactory = new EntityMetadataFactory();
    $this->lookup = new EntityRecordLookup($this->connection, $metadataFactory, new EntityHydrator($metadataFactory));
});

it('implements RecordLookupInterface', function (): void {
    expect($this->lookup)->toBeInstanceOf(RecordLookupInterface::class);
});

it('probes the entity table with SELECT 1 ... LIMIT 1 and reports whether a row came back', function (): void {
    $missing = $this->lookup->exists(LookupChannel::class, 'id', 7);
    $this->connection->rows = [['?column?' => 1]];
    $found = $this->lookup->exists(LookupChannel::class, 'id', 7);

    expect($missing)->toBeFalse()
        ->and($found)->toBeTrue()
        ->and($this->connection->queries[0])->toBe([
            'sql' => 'SELECT 1 FROM "lookup_channels" WHERE "id" = ? LIMIT 1',
            'bindings' => [7],
        ]);
});

it('maps properties to their columns through the entity metadata', function (): void {
    $this->lookup->isTaken(LookupAccount::class, 'email', 'ada@example.com', where: ['tenantId' => 3]);

    expect($this->connection->queries[0])->toBe([
        'sql' => 'SELECT 1 FROM "lookup_accounts" WHERE "email_address" = ? AND "tenant_ref" = ? LIMIT 1',
        'bindings' => ['ada@example.com', 3],
    ]);
});

it('leaves out the ignored id and matches NULL for a null where value', function (): void {
    $this->lookup->isTaken(LookupChannel::class, 'slug', 'news', ignoreId: '4', where: ['notes' => null]);

    expect($this->connection->queries[0])->toBe([
        'sql' => 'SELECT 1 FROM "lookup_channels" WHERE "slug" = ? AND "notes" IS NULL AND "id" <> ? LIMIT 1',
        'bindings' => ['news', 4],
    ]);
});

it('compares with LOWER() on both sides when case-insensitive', function (): void {
    $this->lookup->isTaken(LookupAccount::class, 'email', 'Ada@Example.com', caseInsensitive: true);

    expect($this->connection->queries[0]['sql'])
        ->toBe('SELECT 1 FROM "lookup_accounts" WHERE LOWER("email_address") = LOWER(?) LIMIT 1');
});

it('converts values through the entity casts', function (): void {
    $this->lookup->exists(LookupChannel::class, 'id', '12', ['status' => ChannelStatus::Paused, 'archived' => false]);

    expect($this->connection->queries[0]['bindings'])->toBe([12, 'paused', false]);
});

it('matches nothing, without a query, for a value an integer column cannot hold', function (mixed $value): void {
    expect($this->lookup->exists(LookupChannel::class, 'id', $value))->toBeFalse()
        ->and($this->lookup->isTaken(LookupAccount::class, 'email', 'a@example.com', where: ['tenantId' => $value]))
        ->toBeFalse()
        ->and($this->connection->queries)->toBe([]);
})->with(['word' => ['abc'], 'number prefix' => ['1abc'], 'decimal' => ['1.5'], 'bool' => [true]]);

it('ignores nothing for an ignored id the primary key cannot hold', function (): void {
    $this->lookup->isTaken(LookupChannel::class, 'slug', 'news', ignoreId: 'not-an-id');

    expect($this->connection->queries[0]['sql'])->toBe('SELECT 1 FROM "lookup_channels" WHERE "slug" = ? LIMIT 1');
});

it('throws a helpful error for an unknown entity class', function (): void {
    expect(fn () => $this->lookup->exists('App\Entity\Missing', 'id', 1))
        ->toThrow(RecordLookupException::class, "Cannot look up records of 'App\\Entity\\Missing': no such class");
});

it('throws for a class that is not an entity', function (): void {
    expect(fn () => $this->lookup->exists(RecordingConnection::class, 'id', 1))
        ->toThrow(EntityException::class);
});

it('throws a helpful error naming the entity properties for an unknown property', function (string $where): void {
    $call = $where === 'value'
        ? fn () => $this->lookup->exists(LookupAccount::class, 'email_address', 'a@example.com')
        : fn () => $this->lookup->exists(LookupAccount::class, 'email', 'a@example.com', ['tenant' => 1]);

    try {
        $call();
        $this->fail('Expected a RecordLookupException');
    } catch (RecordLookupException $exception) {
        expect($exception->getMessage())->toContain("Entity '" . LookupAccount::class . "' has no column property")
            ->and($exception->getSuggestion())->toBe('Use one of its #[Column] properties: id, email, tenantId');
    }
})->with(['value' => ['value'], 'where' => ['where']]);

it('refuses a case-insensitive comparison on a property that is not a string', function (): void {
    expect(fn () => $this->lookup->isTaken(LookupAccount::class, 'tenantId', 3, caseInsensitive: true))
        ->toThrow(RecordLookupException::class, "Cannot compare 'tenantId'");
});

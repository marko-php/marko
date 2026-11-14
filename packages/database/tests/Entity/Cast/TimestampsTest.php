<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Cast;

use DateTimeImmutable;
use DateTimeZone;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Timestamps;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Repository\Repository;
use Marko\Database\Tests\Entity\Cast\Fixtures\RecordingSqliteConnection;
use Marko\Testing\Fake\FakeClock;

#[Table('ts_posts')]
#[Timestamps]
class TsPost extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title = '';

    #[Column]
    public DateTimeImmutable $createdAt;

    #[Column]
    public DateTimeImmutable $updatedAt;
}

#[Table('ts_posts')]
#[Timestamps]
class TsNullablePost extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title = '';

    #[Column]
    public ?DateTimeImmutable $createdAt = null;

    #[Column]
    public ?DateTimeImmutable $updatedAt = null;
}

#[Table('ts_renamed')]
#[Timestamps(createdAt: 'born', updatedAt: null)]
class TsRenamed extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title = '';

    #[Column]
    public ?DateTimeImmutable $born = null;

    #[Column]
    public ?DateTimeImmutable $updatedAt = null;
}

#[Table('ts_missing')]
#[Timestamps]
class TsMissingProperty extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public ?DateTimeImmutable $createdAt = null;
}

#[Table('ts_wrong_type')]
#[Timestamps]
class TsWrongType extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $createdAt = '';

    #[Column]
    public ?DateTimeImmutable $updatedAt = null;
}

#[Table('ts_none')]
#[Timestamps(createdAt: null, updatedAt: null)]
class TsBothDisabled extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;
}

#[Table('ts_companion_parent')]
#[Timestamps]
class TsAccount extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $username = '';

    #[Column]
    public ?DateTimeImmutable $createdAt = null;

    #[Column]
    public ?DateTimeImmutable $updatedAt = null;
}

#[Table(extends: TsAccount::class)]
class TsAccountProfile extends Entity
{
    #[Column]
    public string $bio = '';
}

#[Table(extends: TsAccount::class)]
#[Timestamps]
class TsBadExtender extends Entity
{
    #[Column]
    public ?DateTimeImmutable $createdAt = null;
}

class TsPostRepository extends Repository
{
    protected const string ENTITY_CLASS = TsPost::class;
}

class TsNullablePostRepository extends Repository
{
    protected const string ENTITY_CLASS = TsNullablePost::class;
}

class TsRenamedRepository extends Repository
{
    protected const string ENTITY_CLASS = TsRenamed::class;
}

class TsAccountRepository extends Repository
{
    protected const string ENTITY_CLASS = TsAccount::class;
}

function tsConnection(): RecordingSqliteConnection
{
    $connection = new RecordingSqliteConnection();
    $connection->createTable(
        'CREATE TABLE ts_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, created_at TEXT, updated_at TEXT)',
    );
    $connection->createTable(
        'CREATE TABLE ts_renamed (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, born TEXT, updated_at TEXT)',
    );
    $connection->createTable(
        'CREATE TABLE ts_companion_parent (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, '
        . 'created_at TEXT, updated_at TEXT, bio TEXT)',
    );

    return $connection;
}

function tsAt(
    string $time,
): DateTimeImmutable {
    return new DateTimeImmutable($time, new DateTimeZone('UTC'));
}

/**
 * @param class-string<Repository> $repositoryClass
 */
function tsRepository(
    string $repositoryClass,
    RecordingSqliteConnection $connection,
    FakeClock $clock = new FakeClock('2026-01-01 12:00:00 UTC'),
): Repository {
    $metadataFactory = new EntityMetadataFactory();
    $metadataFactory->linkExtenders(TsAccount::class, [TsAccountProfile::class]);

    return new $repositoryClass($connection, $metadataFactory, new EntityHydrator($metadataFactory), clock: $clock);
}

it('sets createdAt and updatedAt on insert', function (): void {
    $connection = tsConnection();
    $repository = tsRepository(TsNullablePostRepository::class, $connection);

    $post = new TsNullablePost();
    $post->title = 'Hello';
    $repository->save($post);

    expect($post->createdAt)->toEqual(tsAt('2026-01-01 12:00:00'))
        ->and($post->updatedAt)->toEqual(tsAt('2026-01-01 12:00:00'))
        ->and($connection->lastInsertValues())->toHaveKeys(['created_at', 'updated_at'])
        ->and($connection->lastInsertValues()['created_at'])->not->toBeNull();
});

it('respects explicitly set timestamp values on insert', function (): void {
    $connection = tsConnection();
    $repository = tsRepository(TsNullablePostRepository::class, $connection);

    $post = new TsNullablePost();
    $post->createdAt = tsAt('2020-05-05 05:05:05');
    $repository->save($post);

    expect($post->createdAt)->toEqual(tsAt('2020-05-05 05:05:05'))
        ->and($post->updatedAt)->toEqual(tsAt('2026-01-01 12:00:00'));
});

it('sets only updatedAt on update', function (): void {
    $connection = tsConnection();
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $repository = tsRepository(TsNullablePostRepository::class, $connection, $clock);
    $post = new TsNullablePost();
    $post->title = 'Hello';
    $repository->save($post);

    $clock->setNow('2026-02-02 08:00:00 UTC');
    $post->title = 'Changed';
    $repository->save($post);

    expect(array_keys($connection->lastUpdateValues()))->toBe(['title', 'updated_at'])
        ->and($post->updatedAt)->toEqual(tsAt('2026-02-02 08:00:00'))
        ->and($post->createdAt)->toEqual(tsAt('2026-01-01 12:00:00'));
});

it('does not touch updatedAt when nothing is dirty', function (): void {
    $connection = tsConnection();
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $repository = tsRepository(TsNullablePostRepository::class, $connection, $clock);
    $post = new TsNullablePost();
    $repository->save($post);
    $updatesBefore = count(array_filter(
        $connection->executed,
        fn (array $entry): bool => str_starts_with($entry['sql'], 'UPDATE'),
    ));

    $clock->setNow('2026-02-02 08:00:00 UTC');
    $repository->save($post);

    $updatesAfter = count(array_filter(
        $connection->executed,
        fn (array $entry): bool => str_starts_with($entry['sql'], 'UPDATE'),
    ));

    expect($updatesAfter)->toBe($updatesBefore)
        ->and($post->updatedAt)->toEqual(tsAt('2026-01-01 12:00:00'));
});

it('throws when a timestamp property is missing or not a DateTimeImmutable column', function (): void {
    $factory = new EntityMetadataFactory();

    expect(fn () => $factory->parse(TsMissingProperty::class))
        ->toThrow(EntityException::class, "updatedAt property 'updatedAt'")
        ->and(fn () => $factory->parse(TsWrongType::class))
        ->toThrow(EntityException::class, "createdAt property 'createdAt'")
        ->and(fn () => $factory->parse(TsBothDisabled::class))
        ->toThrow(EntityException::class, 'disables both');
});

it('sets timestamps on uninitialized non-nullable DateTimeImmutable properties', function (): void {
    $connection = tsConnection();
    $repository = tsRepository(TsPostRepository::class, $connection);

    $post = new TsPost();
    $post->title = 'Hello';
    $repository->save($post);

    expect($post->createdAt)->toEqual(tsAt('2026-01-01 12:00:00'))
        ->and($post->updatedAt)->toEqual(tsAt('2026-01-01 12:00:00'));
});

it('sets timestamps for every entity in insertBatch', function (): void {
    $connection = tsConnection();
    $repository = tsRepository(TsPostRepository::class, $connection);

    $first = new TsPost();
    $first->title = 'One';
    $second = new TsPost();
    $second->title = 'Two';
    $repository->insertBatch([$first, $second]);

    expect($first->createdAt)->toEqual(tsAt('2026-01-01 12:00:00'))
        ->and($second->updatedAt)->toEqual(tsAt('2026-01-01 12:00:00'))
        ->and($connection->query('SELECT created_at FROM ts_posts WHERE created_at IS NOT NULL'))->toHaveCount(2);
});

it('bumps updatedAt when only a companion is dirty', function (): void {
    $connection = tsConnection();
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $repository = tsRepository(TsAccountRepository::class, $connection, $clock);

    $account = new TsAccount();
    $profile = new TsAccountProfile();
    $account->attachCompanion($profile);
    $repository->save($account);

    $clock->setNow('2026-03-03 09:00:00 UTC');
    $profile->bio = 'New bio';
    $repository->save($account);

    expect(array_keys($connection->lastUpdateValues()))->toBe(['bio', 'updated_at'])
        ->and($account->updatedAt)->toEqual(tsAt('2026-03-03 09:00:00'));
});

it('keeps a user-modified updatedAt on update', function (): void {
    $connection = tsConnection();
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $repository = tsRepository(TsNullablePostRepository::class, $connection, $clock);
    $post = new TsNullablePost();
    $repository->save($post);

    $clock->setNow('2026-02-02 08:00:00 UTC');
    $post->updatedAt = tsAt('2019-09-09 09:09:09');
    $repository->save($post);

    expect($post->updatedAt)->toEqual(tsAt('2019-09-09 09:09:09'))
        ->and(array_keys($connection->lastUpdateValues()))->toBe(['updated_at']);
});

it('throws when Timestamps is declared on an extender entity', function (): void {
    new EntityMetadataFactory()->parse(TsBadExtender::class);
})->throws(EntityException::class, 'cannot declare #[Timestamps]');

it('supports renamed and null-disabled timestamp properties', function (): void {
    $connection = tsConnection();
    $repository = tsRepository(TsRenamedRepository::class, $connection);

    $row = new TsRenamed();
    $repository->save($row);

    expect($row->born)->toEqual(tsAt('2026-01-01 12:00:00'))
        ->and($row->updatedAt)->toBeNull();
});

it('stamps created_at and updated_at from the injected clock in UTC', function (): void {
    $connection = tsConnection();
    $repository = tsRepository(
        TsNullablePostRepository::class,
        $connection,
        new FakeClock('2026-10-05 14:00:00+02:00'),
    );

    $post = new TsNullablePost();
    $post->title = 'Clocked';
    $repository->save($post);

    expect($post->createdAt?->format('Y-m-d H:i:s e'))->toBe('2026-10-05 12:00:00 UTC')
        ->and($post->updatedAt?->format('Y-m-d H:i:s e'))->toBe('2026-10-05 12:00:00 UTC');
});

it('falls back to the system clock when no clock is given', function (): void {
    $metadataFactory = new EntityMetadataFactory();
    $repository = new TsNullablePostRepository(tsConnection(), $metadataFactory, new EntityHydrator($metadataFactory));
    $before = new DateTimeImmutable('-1 second');

    $post = new TsNullablePost();
    $post->title = 'System time';
    $repository->save($post);

    expect($post->createdAt?->getTimezone()->getName())->toBe('UTC')
        ->and($post->createdAt >= $before)->toBeTrue()
        ->and($post->createdAt <= new DateTimeImmutable('+1 second'))->toBeTrue();
});

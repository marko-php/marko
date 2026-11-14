<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Cast;

use DateTimeImmutable;
use DateTimeZone;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use Marko\Database\Tests\Entity\Cast\Fixtures\RecordingSqliteConnection;
use ReflectionClass;

enum PipelineStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

#[Table('pipeline_items')]
class PipelineItem extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public int $count = 0;

    #[Column]
    public float $price = 0.0;

    #[Column]
    public bool $active = false;

    #[Column]
    public string $title = '';

    #[Column]
    public PipelineStatus $status = PipelineStatus::Draft;

    #[Column(type: 'datetime')]
    public ?DateTimeImmutable $publishedAt = null;

    #[Column(type: 'json')]
    public array $tags = [];

    #[Column]
    public ?string $note = null;
}

class PipelineItemRepository extends Repository
{
    protected const string ENTITY_CLASS = PipelineItem::class;
}

function pipelineConnection(): RecordingSqliteConnection
{
    $connection = new RecordingSqliteConnection();
    $connection->createTable(
        'CREATE TABLE pipeline_items (id INTEGER PRIMARY KEY AUTOINCREMENT, count INTEGER, price REAL, '
        . 'active INTEGER, title TEXT, status TEXT, published_at TEXT, tags TEXT, note TEXT)',
    );

    return $connection;
}

function fillPipelineItem(
    PipelineItem $item,
): void {
    $item->count = 7;
    $item->price = 19.95;
    $item->active = true;
    $item->title = 'Hello';
    $item->status = PipelineStatus::Published;
    $item->publishedAt = new DateTimeImmutable('2026-03-01 10:15:00', new DateTimeZone('UTC'));
    $item->tags = ['a' => 1, 'b' => [2, 3]];
    $item->note = 'note';
}

it('produces identical database values on insert and update for every built-in type', function (): void {
    $connection = pipelineConnection();
    $repository = new PipelineItemRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $inserted = new PipelineItem();
    fillPipelineItem($inserted);
    $repository->save($inserted);
    $insertValues = $connection->lastInsertValues();

    $updated = new PipelineItem();
    $repository->save($updated);
    fillPipelineItem($updated);
    $repository->save($updated);
    $updateValues = $connection->lastUpdateValues();

    unset($insertValues['id']);
    ksort($insertValues);
    ksort($updateValues);

    expect($updateValues)->toBe($insertValues);
});

it('json-encodes array columns on update', function (): void {
    $connection = pipelineConnection();
    $repository = new PipelineItemRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $item = new PipelineItem();
    $repository->save($item);
    $item->tags = ['x' => 'y'];
    $repository->save($item);

    expect($connection->lastUpdateValues()['tags'])->toBe('{"x":"y"}');
});

it('exposes toDatabaseValue and toPhpValue on the hydrator', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = new EntityMetadataFactory()->parse(PipelineItem::class);
    $status = $metadata->getProperty('status');

    expect($hydrator->toDatabaseValue(PipelineStatus::Published, $status))->toBe('published')
        ->and($hydrator->toPhpValue('draft', $status))->toBe(PipelineStatus::Draft)
        ->and($hydrator->toDatabaseValue(null, $status))->toBeNull()
        ->and($hydrator->toPhpValue(null, $status))->toBeNull();
});

it('no longer declares a private convertToDbValue on Repository', function (): void {
    expect(new ReflectionClass(Repository::class)->hasMethod('convertToDbValue'))->toBeFalse();
});

#[Table('pipeline_accounts')]
class PipelineAccount extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $username = '';
}

#[Table(extends: PipelineAccount::class)]
class PipelineAccountProfile extends Entity
{
    #[Column]
    public PipelineStatus $profileStatus = PipelineStatus::Draft;

    #[Column(type: 'json')]
    public array $settings = [];
}

class PipelineAccountRepository extends Repository
{
    protected const string ENTITY_CLASS = PipelineAccount::class;
}

it('converts dirty companion values through the hydrator on update', function (): void {
    $connection = new RecordingSqliteConnection();
    $connection->createTable(
        'CREATE TABLE pipeline_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, '
        . 'profile_status TEXT, settings TEXT)',
    );
    $metadataFactory = new EntityMetadataFactory();
    $metadataFactory->linkExtenders(PipelineAccount::class, [PipelineAccountProfile::class]);
    $repository = new PipelineAccountRepository($connection, $metadataFactory, new EntityHydrator($metadataFactory));

    $account = new PipelineAccount();
    $account->username = 'mark';
    $profile = new PipelineAccountProfile();
    $account->attachCompanion($profile);
    $repository->save($account);

    $profile->profileStatus = PipelineStatus::Published;
    $profile->settings = ['theme' => 'dark'];
    $repository->save($account);

    expect($connection->lastUpdateValues())->toBe([
        'profile_status' => 'published',
        'settings' => '{"theme":"dark"}',
    ]);
});

it('constructs EntityHydrator with no arguments', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = new EntityMetadataFactory()->parse(PipelineItem::class);

    expect($hydrator->toPhpValue('3', $metadata->getProperty('count')))->toBe(3);
});

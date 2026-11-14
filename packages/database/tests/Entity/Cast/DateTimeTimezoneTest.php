<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Cast;

use DateTimeImmutable;
use DateTimeZone;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Entity\Cast\DateTimeCast;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Exceptions\ConfigurationException;
use Marko\Database\Repository\Repository;
use Marko\Database\Tests\Entity\Cast\Fixtures\RecordingSqliteConnection;

#[Table('appointments')]
class Appointment extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(type: 'datetime')]
    public DateTimeImmutable $startsAt;
}

class AppointmentRepository extends Repository
{
    protected const string ENTITY_CLASS = Appointment::class;
}

function timezoneConfigDir(
    ?string $timezone,
): string {
    $dir = sys_get_temp_dir() . '/marko-tz-' . bin2hex(random_bytes(4));
    mkdir($dir . '/config', 0777, true);
    $config = $timezone === null ? "<?php return ['driver' => 'mysql'];" : "<?php return ['timezone' => '$timezone'];";
    file_put_contents($dir . '/config/database.php', $config);

    return $dir;
}

it('stores an America/New_York datetime as the UTC instant', function (): void {
    $connection = new RecordingSqliteConnection();
    $connection->createTable('CREATE TABLE appointments (id INTEGER PRIMARY KEY AUTOINCREMENT, starts_at TEXT)');
    $repository = new AppointmentRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $appointment = new Appointment();
    $appointment->startsAt = new DateTimeImmutable('2026-07-04 09:30:00', new DateTimeZone('America/New_York'));
    $repository->save($appointment);

    expect($connection->lastInsertValues()['starts_at'])->toBe('2026-07-04 13:30:00');
});

it('reads a stored datetime back as the same instant', function (): void {
    $connection = new RecordingSqliteConnection();
    $connection->createTable('CREATE TABLE appointments (id INTEGER PRIMARY KEY AUTOINCREMENT, starts_at TEXT)');
    $repository = new AppointmentRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $original = new DateTimeImmutable('2026-07-04 09:30:00', new DateTimeZone('America/New_York'));
    $appointment = new Appointment();
    $appointment->startsAt = $original;
    $repository->save($appointment);

    $previousTimezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Tokyo');

    try {
        $loaded = $repository->find($appointment->id);
    } finally {
        date_default_timezone_set($previousTimezone);
    }

    expect($loaded->startsAt->getTimestamp())->toBe($original->getTimestamp())
        ->and($loaded->startsAt->getTimezone()->getName())->toBe('UTC');
});

it('uses the configured database timezone', function (): void {
    $config = new DatabaseTimezoneConfig(new ProjectPaths(timezoneConfigDir('America/Chicago')));
    $cast = new DateTimeCast($config);
    $meta = new PropertyMetadata('at', 'at', DateTimeImmutable::class);

    $utc = new DateTimeImmutable('2026-01-15 18:00:00', new DateTimeZone('UTC'));

    expect($cast->toDatabase($utc, $meta))->toBe('2026-01-15 12:00:00')
        ->and($cast->toPhp('2026-01-15 12:00:00', $meta)->getTimestamp())->toBe($utc->getTimestamp());
});

it('defaults the database timezone to UTC when the config key is absent', function (): void {
    $config = new DatabaseTimezoneConfig(new ProjectPaths(timezoneConfigDir(null)));

    expect($config->timezone->getName())->toBe('UTC')
        ->and(DatabaseTimezoneConfig::fromName('Europe/Paris')->timezone->getName())->toBe('Europe/Paris');
});

it('throws a configuration exception for an invalid timezone', function (): void {
    expect(fn () => new DatabaseTimezoneConfig(new ProjectPaths(timezoneConfigDir('Mars/Olympus'))))
        ->toThrow(ConfigurationException::class, 'Invalid database timezone: Mars/Olympus');
});

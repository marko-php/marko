<?php

declare(strict_types=1);

namespace Marko\Queue\Database\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * Owns the schema of the jobs table, so db:migrate creates it and db:diff reports drift. DatabaseQueue reads and
 * writes the table with its own SQL, not through this entity.
 */
#[Table('jobs')]
#[Index(['queue', 'available_at'])]
class DatabaseJob extends Entity
{
    #[Column(type: 'varchar', length: 36, primaryKey: true)]
    public string $id;

    #[Column(type: 'varchar', length: 255, default: 'default')]
    public string $queue = 'default';

    #[Column(type: 'text')]
    public string $payload;

    #[Column(type: 'int', default: 0)]
    public int $attempts = 0;

    #[Column(type: 'timestamp')]
    public ?string $reservedAt = null;

    #[Column(type: 'timestamp')]
    public string $availableAt;

    #[Column(type: 'timestamp', default: 'CURRENT_TIMESTAMP')]
    public string $createdAt;
}

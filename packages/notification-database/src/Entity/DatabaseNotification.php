<?php

declare(strict_types=1);

namespace Marko\Notification\Database\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(name: 'notifications')]
class DatabaseNotification extends Entity
{
    #[Column(type: 'varchar', length: 36, primaryKey: true)]
    public string $id;

    #[Column(type: 'varchar', length: 255)]
    public string $type;

    #[Column(type: 'varchar', length: 255)]
    public string $notifiableType;

    #[Column(type: 'varchar', length: 255)]
    public string $notifiableId;

    #[Column(type: 'text')]
    public string $data;

    #[Column(type: 'timestamp')]
    public ?string $readAt = null;

    #[Column(type: 'timestamp')]
    public string $createdAt;
}

<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Tests\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('lookup_channels')]
class LookupChannel extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 100)]
    public string $slug;

    #[Column(name: 'display_name', length: 100)]
    public string $title;

    #[Column]
    public bool $archived = false;

    #[Column(length: 20)]
    public ChannelStatus $status = ChannelStatus::Live;

    #[Column(type: 'text')]
    public ?string $notes = null;
}

<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Integration\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('int_users')]
class IntUser extends Entity
{
    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(length: 255)]
    public string $name;

    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(length: 255)]
    public string $email;
}

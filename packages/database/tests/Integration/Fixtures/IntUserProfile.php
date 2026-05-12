<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Integration\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(extends: IntUser::class)]
class IntUserProfile extends Entity
{
    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(length: 255, nullable: true)]
    public ?string $bio = null;

    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(length: 100, nullable: true)]
    public ?string $timezone = null;
}

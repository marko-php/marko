<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Integration\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * Second extender — used only in the conflict-detection test.
 * Declares a column named 'bio' which conflicts with IntUserProfile.
 */
#[Table(extends: IntUser::class)]
class IntUserSettings extends Entity
{
    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(length: 255, nullable: true)]
    public ?string $bio = null;
}

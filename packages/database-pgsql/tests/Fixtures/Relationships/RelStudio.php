<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\Relationships;

use Marko\Database\Attributes\BelongsToMany;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * A studio with one profile, many projects and many tags (through RelStudioTag), for the whereHas() tests.
 */
#[Table('rel_studios')]
class RelStudio extends Entity
{
    #[Column(primaryKey: true)]
    public int $id;

    #[Column]
    public string $name;

    #[HasOne(RelStudioProfile::class, foreignKey: 'studioId')]
    public ?RelStudioProfile $profile = null;

    /** @var array<RelProject> */
    #[HasMany(RelProject::class, foreignKey: 'studioId')]
    public array $projects = [];

    /** @var array<RelTag> */
    #[BelongsToMany(RelTag::class, pivotClass: RelStudioTag::class, foreignKey: 'studioId', relatedKey: 'tagId')]
    public array $tags = [];
}

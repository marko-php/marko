<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

enum RelationshipType: string
{
    case HasOne = 'has_one';
    case HasMany = 'has_many';
    case BelongsTo = 'belongs_to';
    case BelongsToMany = 'belongs_to_many';
}

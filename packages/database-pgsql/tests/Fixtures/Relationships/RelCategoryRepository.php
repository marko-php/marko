<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\Relationships;

use Marko\Database\Repository\Repository;

class RelCategoryRepository extends Repository
{
    protected const string ENTITY_CLASS = RelCategory::class;
}

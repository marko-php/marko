<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\Variant;

use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;

class VariantQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    public function create(): QueryBuilderInterface
    {
        return new VariantQueryBuilder();
    }
}

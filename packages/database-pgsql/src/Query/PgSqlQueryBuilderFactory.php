<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;

class PgSqlQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    public function create(): QueryBuilderInterface
    {
        return new PgSqlQueryBuilder(
            connection: $this->connection,
        );
    }
}

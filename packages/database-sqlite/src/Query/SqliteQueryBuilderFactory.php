<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;

readonly class SqliteQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function create(): QueryBuilderInterface
    {
        return new SqliteQueryBuilder(
            connection: $this->connection,
            queryBuilderFactory: $this,
        );
    }
}

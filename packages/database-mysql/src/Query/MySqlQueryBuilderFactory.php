<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;

class MySqlQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    public function create(): QueryBuilderInterface
    {
        return new MySqlQueryBuilder(
            connection: $this->connection,
        );
    }
}

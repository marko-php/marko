<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\MySql\Connection\MySqlServer;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;

readonly class MySqlQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    private MySqlServer $server;

    /**
     * @param MySqlServer|null $server The shared server check, passed to every builder; built from $connection
     *     when omitted
     */
    public function __construct(
        private ConnectionInterface $connection,
        ?MySqlServer $server = null,
    ) {
        $this->server = $server ?? new MySqlServer($connection);
    }

    public function create(): QueryBuilderInterface
    {
        return new MySqlQueryBuilder(
            connection: $this->connection,
            server: $this->server,
            queryBuilderFactory: $this,
        );
    }
}

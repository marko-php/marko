<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

use Marko\Database\Config\DatabaseConfig;

interface ConnectionFactoryInterface
{
    public function make(DatabaseConfig $config): ConnectionInterface;
}

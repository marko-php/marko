<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime\Contracts;

interface QueryConnectionInterface
{
    /** @return list<array<string, mixed>> */
    public function query(string $sql, array $params = []): array;
}

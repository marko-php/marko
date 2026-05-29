<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime\Contracts;

interface LogReaderInterface
{
    /** @return list<string> */
    public function readLast(int $count): array;
}

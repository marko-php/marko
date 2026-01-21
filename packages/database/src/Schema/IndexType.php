<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

enum IndexType: string
{
    case Btree = 'btree';
    case Unique = 'unique';
    case Fulltext = 'fulltext';
}

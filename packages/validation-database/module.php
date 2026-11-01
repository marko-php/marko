<?php

declare(strict_types=1);

use Marko\Validation\Contracts\RecordLookupInterface;
use Marko\Validation\Database\EntityRecordLookup;

return [
    'bindings' => [
        RecordLookupInterface::class => EntityRecordLookup::class,
    ],
];

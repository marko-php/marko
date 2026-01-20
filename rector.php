<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\FuncCall\StrContainsRector;
use Rector\Php80\Rector\FuncCall\StrEndsWithRector;
use Rector\Php80\Rector\FuncCall\StrStartsWithRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages',
        __DIR__ . '/demo/app',
        __DIR__ . '/demo/modules',
    ])
    ->withRules([
        ClassPropertyAssignToConstructorPromotionRector::class,
        StrStartsWithRector::class,
        StrEndsWithRector::class,
        StrContainsRector::class,
    ]);

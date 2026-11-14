<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\Identical\StrEndsWithRector;
use Rector\Php80\Rector\Identical\StrStartsWithRector;
use Rector\Php80\Rector\NotIdentical\StrContainsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages',
    ])
    ->withSkip([
        // Deliberate syntax errors, also excluded in .php-cs-fixer.php
        __DIR__ . '/packages/config/tests/Unit/fixtures/syntax-error.php',
        __DIR__ . '/packages/codeindexer/tests/Fixtures/AttributeFixtures/src/Broken/SyntaxError.php',
        __DIR__ . '/packages/codeindexer/tests/Fixtures/ConfigFixtures/module-d/config/broken.php',
    ])
    ->withRules([
        ClassPropertyAssignToConstructorPromotionRector::class,
        StrStartsWithRector::class,
        StrEndsWithRector::class,
        StrContainsRector::class,
    ]);

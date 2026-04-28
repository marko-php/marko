<?php

declare(strict_types=1);

use Marko\CodeIndexer\Attributes\AttributeParser;
use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Config\ConfigScanner;
use Marko\CodeIndexer\Contract\AttributeParserInterface;
use Marko\CodeIndexer\Contract\ConfigScannerInterface;
use Marko\CodeIndexer\Contract\IndexCacheInterface;
use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\CodeIndexer\Contract\TemplateScannerInterface;
use Marko\CodeIndexer\Contract\TranslationScannerInterface;
use Marko\CodeIndexer\Module\ModuleWalker;
use Marko\CodeIndexer\Translations\TranslationScanner;
use Marko\CodeIndexer\Views\TemplateScanner;

return [
    'bindings' => [
        AttributeParserInterface::class => AttributeParser::class,
        ConfigScannerInterface::class => ConfigScanner::class,
        ModuleWalkerInterface::class => ModuleWalker::class,
        TemplateScannerInterface::class => TemplateScanner::class,
        TranslationScannerInterface::class => TranslationScanner::class,
        IndexCacheInterface::class => IndexCache::class,
    ],
    'singletons' => [
        IndexCache::class,
        ModuleWalkerInterface::class,
    ],
];

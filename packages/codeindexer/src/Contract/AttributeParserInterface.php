<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Contract;

use Marko\CodeIndexer\ValueObject\CommandEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\ObserverEntry;
use Marko\CodeIndexer\ValueObject\PluginEntry;
use Marko\CodeIndexer\ValueObject\PreferenceEntry;
use Marko\CodeIndexer\ValueObject\RouteEntry;

interface AttributeParserInterface
{
    /** @return list<ObserverEntry> */
    public function observers(ModuleInfo $module): array;

    /** @return list<PluginEntry> */
    public function plugins(ModuleInfo $module): array;

    /** @return list<PreferenceEntry> */
    public function preferences(ModuleInfo $module): array;

    /** @return list<CommandEntry> */
    public function commands(ModuleInfo $module): array;

    /** @return list<RouteEntry> */
    public function routes(ModuleInfo $module): array;
}

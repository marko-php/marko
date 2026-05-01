<?php

declare(strict_types=1);

use Marko\Debugbar\Debugbar;
use Marko\Debugbar\Plugins\DatabaseConnectionPlugin;
use Marko\Debugbar\Plugins\LoggerPlugin;
use Marko\Debugbar\Plugins\ViewPlugin;
use Marko\Debugbar\Storage\DebugbarStorage;

return [
    'singletons' => [
        Debugbar::class,
        DebugbarStorage::class,
        DatabaseConnectionPlugin::class,
        LoggerPlugin::class,
        ViewPlugin::class,
    ],
    'boot' => static function (Debugbar $debugbar): void {
        $debugbar->boot();
    },
];

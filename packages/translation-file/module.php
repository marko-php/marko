<?php

declare(strict_types=1);

use Marko\Translation\Contracts\TranslationLoaderInterface;
use Marko\Translation\File\Loader\FileTranslationLoader;

return [
    'bindings' => [
        TranslationLoaderInterface::class => FileTranslationLoader::class,
    ],
];

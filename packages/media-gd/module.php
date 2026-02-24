<?php

declare(strict_types=1);

use Marko\Media\Contracts\ImageProcessorInterface;
use Marko\MediaGd\Driver\GdImageProcessor;

return [
    'bindings' => [
        ImageProcessorInterface::class => GdImageProcessor::class,
    ],
];

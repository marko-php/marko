<?php

declare(strict_types=1);

use Marko\Media\Contracts\ImageProcessorInterface;
use Marko\MediaImagick\Driver\ImagickImageProcessor;

return [
    'bindings' => [
        ImageProcessorInterface::class => ImagickImageProcessor::class,
    ],
];

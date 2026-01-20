<?php

declare(strict_types=1);

use Marko\Blog\Contracts\FormatterInterface;
use Marko\Blog\Formatter\MarkdownFormatter;

return [
    'bindings' => [
        FormatterInterface::class => MarkdownFormatter::class,
    ],
];

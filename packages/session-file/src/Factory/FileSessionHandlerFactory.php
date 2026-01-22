<?php

declare(strict_types=1);

namespace Marko\Session\File\Factory;

use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Handler\FileSessionHandler;

readonly class FileSessionHandlerFactory
{
    public function __construct(
        private SessionConfig $config,
    ) {}

    public function create(): SessionHandlerInterface
    {
        $path = $this->config->path();

        if (!str_starts_with($path, '/')) {
            $path = getcwd() . '/' . $path;
        }

        return new FileSessionHandler($path);
    }
}

<?php

declare(strict_types=1);

namespace Marko\Core\Path;

/**
 * Provides standard project directory paths.
 *
 * Centralizes path resolution so all framework components
 * use consistent paths. Defaults to getcwd() as base path,
 * which works for both CLI and web contexts.
 */
readonly class ProjectPaths
{
    public string $base;

    public string $vendor;

    public string $modules;

    public string $app;

    public string $config;

    public string $database;

    public function __construct(
        ?string $basePath = null,
    ) {
        $this->base = $basePath ?? getcwd();
        $this->vendor = $this->base . '/vendor';
        $this->modules = $this->base . '/modules';
        $this->app = $this->base . '/app';
        $this->config = $this->base . '/config';
        $this->database = $this->base . '/database';
    }
}

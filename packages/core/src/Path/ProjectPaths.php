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

    /**
     * Resolve a path against the project root.
     *
     * Absolute paths (see isAbsolute()) are returned unchanged; relative
     * paths are joined onto the base path. Use this instead of getcwd():
     * FPM, CGI and mod_php chdir into public/ before running the front controller.
     */
    public function resolve(
        string $path,
    ): string {
        if (self::isAbsolute($path)) {
            return $path;
        }

        return $this->base . '/' . $path;
    }

    /**
     * Whether a path is absolute on any supported platform.
     *
     * Recognizes Unix paths ('/var/cache'), Windows drive paths ('C:\cache'
     * or 'C:/cache') and UNC paths ('\\server\share'). A drive-relative path
     * such as 'C:cache' is not absolute.
     */
    public static function isAbsolute(
        string $path,
    ): bool {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}

<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class FileResponseException extends MarkoException
{
    public static function fileNotFound(
        string $path,
    ): self {
        return new self(
            message: "File '$path' does not exist",
            context: 'While creating a LocalFileSource for a file response',
            suggestion: 'Check the path, or answer with HttpException::notFound() when the file is optional. Build request-derived paths with SafePath::join()',
        );
    }

    public static function notAFile(
        string $path,
    ): self {
        return new self(
            message: "Path '$path' is not a regular file",
            context: 'While creating a LocalFileSource for a file response',
            suggestion: 'Point the LocalFileSource at a file, not a directory',
        );
    }

    public static function unreadable(
        string $path,
    ): self {
        return new self(
            message: "File '$path' is not readable",
            context: 'While creating a LocalFileSource for a file response',
            suggestion: 'Check the file permissions for the user PHP runs as',
        );
    }

    public static function cannotOpen(
        string $path,
    ): self {
        return new self(
            message: "Failed to open '$path' for reading",
            context: 'While streaming a file response',
            suggestion: 'Check that the file still exists and is readable by the user PHP runs as',
        );
    }

    public static function readFailed(): self
    {
        return new self(
            message: 'Reading the file stream failed part way through a file response',
            context: 'While streaming a file response',
            suggestion: 'Check that the file was not removed or truncated while it was being sent',
        );
    }

    public static function invalidOffloadHeader(
        string $header,
    ): self {
        return new self(
            message: "Unsupported file offload header '$header'",
            context: 'While reading routing.file_offload.header',
            suggestion: "Set routing.file_offload.header to 'X-Sendfile' (Apache, lighttpd) or 'X-Accel-Redirect' (nginx), or to '' to stream files from PHP",
        );
    }

    public static function missingOffloadPrefixMap(): self
    {
        return new self(
            message: 'X-Accel-Redirect needs routing.file_offload.prefix_map',
            context: 'While reading routing.file_offload',
            suggestion: "nginx takes a URI, not a file path: map each served directory to an internal location, e.g. ['/var/www/storage/videos/' => '/protected-videos/']",
        );
    }

    public static function offloadPathNotMapped(
        string $path,
    ): self {
        return new self(
            message: "File '$path' is not under any prefix in routing.file_offload.prefix_map",
            context: 'While building the file offload header for a file response',
            suggestion: 'Add the directory holding this file to routing.file_offload.prefix_map, mapped to the internal location your web server serves it from',
        );
    }
}

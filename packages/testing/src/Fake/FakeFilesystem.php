<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use finfo;
use Marko\Filesystem\Contracts\DirectoryListingInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FileNotFoundException;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\Exceptions\PathException;
use Marko\Filesystem\Path\BackslashPolicy;
use Marko\Filesystem\Path\PathNormalizer;
use Marko\Filesystem\Values\DirectoryEntry;
use Marko\Filesystem\Values\DirectoryListing;
use Marko\Filesystem\Values\FileInfo;
use Marko\Testing\Exceptions\AssertionFailedException;
use Psr\Clock\ClockInterface;

/**
 * An in-memory disk that behaves like a real driver and records what code stores on it.
 *
 * Paths follow the shared PathNormalizer rules with backslashes rejected, the strictest rule any
 * driver applies, so a path that passes here works on every disk. Writing a file creates its
 * parent directories, and directories stay after their last file is deleted, as on a local disk.
 *
 * Put it in front of the code under test with FilesystemManagerInterface::useDisk().
 */
class FakeFilesystem implements FilesystemInterface
{
    /**
     * Directories report the visibility a local disk gives them (0755): public.
     */
    private const string DIRECTORY_VISIBILITY = 'public';

    /**
     * Every call that stored contents (write, writeStream, append, copy, move), by destination path.
     *
     * @var list<string>
     */
    public private(set) array $written = [];

    /**
     * @var array<string, array{contents: string, visibility: string, lastModified: int}>
     */
    private array $files = [];

    /**
     * Keyed by normalized path; '' is the disk root.
     *
     * @var array<string, array{visibility: string, lastModified: int}>
     */
    private array $directories = [];

    private PathNormalizer $pathNormalizer;

    /**
     * @param bool $public Visibility of new files when a write passes no visibility option
     */
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly bool $public,
    ) {
        $this->pathNormalizer = new PathNormalizer(BackslashPolicy::Reject);
        $this->clear();
    }

    /**
     * @throws PathException
     */
    public function exists(
        string $path,
    ): bool {
        $normalized = $this->normalize($path);

        return isset($this->files[$normalized]) || isset($this->directories[$normalized]);
    }

    /**
     * @throws PathException
     */
    public function isFile(
        string $path,
    ): bool {
        return isset($this->files[$this->normalize($path)]);
    }

    /**
     * @throws PathException
     */
    public function isDirectory(
        string $path,
    ): bool {
        return isset($this->directories[$this->normalize($path)]);
    }

    /**
     * @throws FileNotFoundException|PathException
     */
    public function info(
        string $path,
    ): FileInfo {
        $normalized = $this->normalize($path);

        if (isset($this->directories[$normalized])) {
            return new FileInfo(
                path: $path,
                size: 0,
                lastModified: $this->directories[$normalized]['lastModified'],
                mimeType: 'directory',
                isDirectory: true,
                visibility: $this->directories[$normalized]['visibility'],
            );
        }

        $file = $this->file($normalized, $path);

        return new FileInfo(
            path: $path,
            size: strlen($file['contents']),
            lastModified: $file['lastModified'],
            mimeType: $this->detectMimeType($file['contents']),
            isDirectory: false,
            visibility: $file['visibility'],
        );
    }

    /**
     * @throws FileNotFoundException|PathException
     */
    public function read(
        string $path,
    ): string {
        return $this->file($this->normalize($path), $path)['contents'];
    }

    /**
     * @throws FileNotFoundException|PathException|FilesystemException
     */
    public function readStream(
        string $path,
    ): mixed {
        $contents = $this->read($path);
        $stream = fopen('php://memory', 'r+b');

        if ($stream === false) {
            throw new FilesystemException(
                message: "Failed to open file stream: '$path'",
                context: 'fopen(php://memory) returned false',
                suggestion: 'Check the PHP memory limit',
            );
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * @throws PathException|FilesystemException
     */
    public function write(
        string $path,
        string $contents,
        array $options = [],
    ): bool {
        $this->store($path, $contents, $options['visibility'] ?? $this->defaultVisibility());

        return true;
    }

    /**
     * @throws PathException|FilesystemException
     */
    public function writeStream(
        string $path,
        mixed $resource,
        array $options = [],
    ): bool {
        $contents = stream_get_contents($resource);

        if ($contents === false) {
            throw new FilesystemException(
                message: 'Failed to read from stream',
                context: 'stream_get_contents returned false',
                suggestion: 'Ensure the stream is readable and positioned correctly',
            );
        }

        return $this->write($path, $contents, $options);
    }

    /**
     * @throws PathException|FilesystemException
     */
    public function append(
        string $path,
        string $contents,
    ): bool {
        $existing = $this->files[$this->normalize($path)] ?? null;

        $this->store(
            $path,
            ($existing['contents'] ?? '') . $contents,
            $existing['visibility'] ?? $this->defaultVisibility(),
        );

        return true;
    }

    /**
     * @throws PathException|FilesystemException
     */
    public function delete(
        string $path,
    ): bool {
        $normalized = $this->normalize($path);

        if (isset($this->directories[$normalized])) {
            throw new FilesystemException(
                message: "Cannot delete '$path': it is a directory",
                context: 'delete() removes files only',
                suggestion: 'Use deleteDirectory() to remove a directory and its contents',
            );
        }

        unset($this->files[$normalized]);

        return true;
    }

    /**
     * @throws FileNotFoundException|PathException|FilesystemException
     */
    public function copy(
        string $source,
        string $destination,
    ): bool {
        $sourcePath = $this->normalize($source);
        $this->normalize($destination);

        if (isset($this->directories[$sourcePath])) {
            throw new FilesystemException(
                message: "Cannot copy '$source': it is a directory",
                context: 'copy() copies files only',
                suggestion: 'Copy the files in the directory one by one',
            );
        }

        $file = $this->file($sourcePath, $source);
        $this->store($destination, $file['contents'], $file['visibility']);

        return true;
    }

    /**
     * @throws FileNotFoundException|PathException|FilesystemException
     */
    public function move(
        string $source,
        string $destination,
    ): bool {
        $sourcePath = $this->normalize($source);
        $this->normalize($destination);

        if (isset($this->directories[$sourcePath]) && $sourcePath !== '') {
            $this->moveDirectory($sourcePath, $destination);

            return true;
        }

        $file = $this->file($sourcePath, $source);
        $this->store($destination, $file['contents'], $file['visibility']);
        unset($this->files[$sourcePath]);

        return true;
    }

    /**
     * @throws FileNotFoundException|PathException
     */
    public function size(
        string $path,
    ): int {
        return $this->info($path)->size;
    }

    /**
     * @throws FileNotFoundException|PathException
     */
    public function lastModified(
        string $path,
    ): int {
        return $this->info($path)->lastModified;
    }

    /**
     * @throws FileNotFoundException|PathException
     */
    public function mimeType(
        string $path,
    ): string {
        return $this->info($path)->mimeType;
    }

    /**
     * @throws FileNotFoundException|PathException|FilesystemException
     */
    public function listDirectory(
        string $path = '/',
    ): DirectoryListingInterface {
        $normalized = $this->normalize($path);

        if (!isset($this->directories[$normalized])) {
            if (!isset($this->files[$normalized])) {
                throw FileNotFoundException::forPath($path);
            }

            throw new FilesystemException(
                message: "Path is not a directory: '$path'",
                context: 'listDirectory called on a file',
                suggestion: 'Use listDirectory only on directories',
            );
        }

        $entries = [];

        foreach ($this->children($normalized) as $child) {
            $file = $this->files[$child] ?? null;

            $entries[] = new DirectoryEntry(
                path: $child,
                isDirectory: $file === null,
                size: $file === null ? 0 : strlen($file['contents']),
                lastModified: $file['lastModified'] ?? $this->directories[$child]['lastModified'],
            );
        }

        return new DirectoryListing($entries);
    }

    /**
     * @throws PathException|FilesystemException
     */
    public function makeDirectory(
        string $path,
    ): bool {
        $normalized = $this->normalize($path);

        if ($normalized === '') {
            return true;
        }

        $this->ensureDirectories($this->ancestors($normalized), $path);
        $this->ensureDirectories([$normalized], $path);

        return true;
    }

    /**
     * @throws PathException
     */
    public function deleteDirectory(
        string $path,
    ): bool {
        $normalized = $this->pathNormalizer->normalizeForDeletion($path);

        if (!isset($this->directories[$normalized])) {
            return true;
        }

        $prefix = $normalized . '/';

        foreach (array_keys($this->files) as $file) {
            if (str_starts_with((string) $file, $prefix)) {
                unset($this->files[$file]);
            }
        }

        foreach (array_keys($this->directories) as $directory) {
            $directory = (string) $directory;

            if ($directory === $normalized || str_starts_with($directory, $prefix)) {
                unset($this->directories[$directory]);
            }
        }

        return true;
    }

    /**
     * @throws FileNotFoundException|PathException
     */
    public function setVisibility(
        string $path,
        string $visibility,
    ): bool {
        $normalized = $this->normalize($path);

        if (isset($this->directories[$normalized])) {
            $this->directories[$normalized]['visibility'] = $visibility;

            return true;
        }

        $this->file($normalized, $path);
        $this->files[$normalized]['visibility'] = $visibility;

        return true;
    }

    /**
     * @throws FileNotFoundException|PathException
     */
    public function visibility(
        string $path,
    ): string {
        return $this->info($path)->visibility;
    }

    /**
     * Remove every file and directory and forget every recorded write.
     */
    public function clear(): void
    {
        $this->files = [];
        $this->directories = [
            '' => [
                'visibility' => self::DIRECTORY_VISIBILITY,
                'lastModified' => $this->clock->now()->getTimestamp(),
            ],
        ];
        $this->written = [];
    }

    /**
     * @throws AssertionFailedException|PathException
     */
    public function assertExists(
        string $path,
    ): void {
        if (!$this->exists($path)) {
            throw AssertionFailedException::expectedPathExists($path);
        }
    }

    /**
     * @throws AssertionFailedException|PathException
     */
    public function assertMissing(
        string $path,
    ): void {
        if ($this->exists($path)) {
            throw AssertionFailedException::unexpectedPathExists($path);
        }
    }

    /**
     * @throws AssertionFailedException|PathException
     */
    public function assertContent(
        string $path,
        string $expected,
    ): void {
        $file = $this->files[$this->normalize($path)] ?? throw AssertionFailedException::expectedPathExists($path);

        if ($file['contents'] !== $expected) {
            throw AssertionFailedException::unexpectedContent($path, $expected, $file['contents']);
        }
    }

    /**
     * @throws AssertionFailedException|PathException
     */
    public function assertDirectoryEmpty(
        string $path,
    ): void {
        $normalized = $this->normalize($path);

        if (!isset($this->directories[$normalized])) {
            throw AssertionFailedException::expectedDirectoryExists($path);
        }

        $children = $this->children($normalized);

        if ($children !== []) {
            throw AssertionFailedException::expectedDirectoryEmpty($path, $children);
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertWrittenCount(
        int $expected,
    ): void {
        $actual = count($this->written);

        if ($actual !== $expected) {
            throw AssertionFailedException::expectedCount('files written', $expected, $actual);
        }
    }

    /**
     * @throws PathException
     */
    private function normalize(
        string $path,
    ): string {
        return $this->pathNormalizer->normalize($path);
    }

    private function defaultVisibility(): string
    {
        return $this->public ? 'public' : 'private';
    }

    /**
     * @return array{contents: string, visibility: string, lastModified: int}
     * @throws FileNotFoundException
     */
    private function file(
        string $normalized,
        string $path,
    ): array {
        return $this->files[$normalized] ?? throw FileNotFoundException::forPath($path);
    }

    /**
     * @throws PathException|FilesystemException
     */
    private function store(
        string $path,
        string $contents,
        string $visibility,
    ): void {
        $normalized = $this->normalize($path);

        if (isset($this->directories[$normalized])) {
            throw new FilesystemException(
                message: "Cannot write to '$path': it is a directory",
                context: 'A file cannot replace a directory',
                suggestion: 'Write to a path inside the directory, or delete the directory first',
            );
        }

        $this->ensureDirectories($this->ancestors($normalized), $path);

        $this->files[$normalized] = [
            'contents' => $contents,
            'visibility' => $visibility,
            'lastModified' => $this->clock->now()->getTimestamp(),
        ];
        $this->written[] = $normalized;
    }

    /**
     * @throws PathException|FilesystemException
     */
    private function moveDirectory(
        string $source,
        string $destination,
    ): void {
        $target = $this->normalize($destination);

        if ($this->exists($target)) {
            throw new FilesystemException(
                message: "Cannot move '$source' to '$destination': the destination exists",
                context: 'Moving a directory never merges it into another path',
                suggestion: 'Delete the destination first, or move the directory to a new path',
            );
        }

        $this->ensureDirectories($this->ancestors($target), $destination);
        $length = strlen($source);

        foreach ($this->directories as $directory => $attributes) {
            $directory = (string) $directory;

            if ($directory === $source || str_starts_with($directory, $source . '/')) {
                unset($this->directories[$directory]);
                $this->directories[$target . substr($directory, $length)] = $attributes;
            }
        }

        foreach ($this->files as $file => $attributes) {
            $file = (string) $file;

            if (str_starts_with($file, $source . '/')) {
                unset($this->files[$file]);
                $this->files[$target . substr($file, $length)] = $attributes;
            }
        }

        $this->written[] = $target;
    }

    /**
     * The parent directories of a normalized path, outermost first, excluding the root.
     *
     * @return list<string>
     */
    private function ancestors(
        string $normalized,
    ): array {
        $segments = explode('/', $normalized);
        array_pop($segments);
        $ancestors = [];
        $current = '';

        foreach ($segments as $segment) {
            $current = $current === '' ? $segment : $current . '/' . $segment;
            $ancestors[] = $current;
        }

        return $ancestors;
    }

    /**
     * @param list<string> $directories
     * @throws FilesystemException
     */
    private function ensureDirectories(
        array $directories,
        string $path,
    ): void {
        foreach ($directories as $directory) {
            if (isset($this->files[$directory])) {
                throw new FilesystemException(
                    message: "Cannot create '$path': '$directory' is a file",
                    context: 'A directory cannot be created beneath a file',
                    suggestion: 'Use a path whose parent segments are directories',
                );
            }

            $this->directories[$directory] ??= [
                'visibility' => self::DIRECTORY_VISIBILITY,
                'lastModified' => $this->clock->now()->getTimestamp(),
            ];
        }
    }

    /**
     * The normalized paths of the files and directories directly inside a directory, sorted.
     *
     * @return list<string>
     */
    private function children(
        string $directory,
    ): array {
        $prefix = $directory === '' ? '' : $directory . '/';
        $children = [];

        foreach ([...array_keys($this->files), ...array_keys($this->directories)] as $path) {
            $path = (string) $path;

            if ($path === '' || !str_starts_with($path, $prefix)) {
                continue;
            }

            if (!str_contains(substr($path, strlen($prefix)), '/')) {
                $children[] = $path;
            }
        }

        sort($children, SORT_STRING);

        return $children;
    }

    private function detectMimeType(
        string $contents,
    ): string {
        $mimeType = new finfo(FILEINFO_MIME_TYPE)->buffer($contents);

        return $mimeType !== false ? $mimeType : 'application/octet-stream';
    }
}

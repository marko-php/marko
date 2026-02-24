<?php

declare(strict_types=1);

namespace Marko\Filesystem\S3\Filesystem;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use DateTimeImmutable;
use DateTimeInterface;
use Marko\Filesystem\Contracts\DirectoryListingInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FileNotFoundException;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\S3\Config\S3Config;
use Marko\Filesystem\Values\DirectoryEntry;
use Marko\Filesystem\Values\DirectoryListing;
use Marko\Filesystem\Values\FileInfo;
use Psr\Http\Message\RequestInterface;
use Throwable;

class S3Filesystem implements FilesystemInterface
{
    /**
     * @var array<string, string>
     */
    private const array MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'pdf' => 'application/pdf',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'zip' => 'application/zip',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'webm' => 'video/webm',
        'avi' => 'video/x-msvideo',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'yaml' => 'text/yaml',
        'yml' => 'text/yaml',
        'md' => 'text/markdown',
    ];

    public function __construct(
        private readonly S3Client $client,
        private readonly S3Config $config,
    ) {}

    public function exists(
        string $path,
    ): bool {
        try {
            $this->client->headObject([
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
            ]);

            return true;
        } catch (S3Exception) {
            return false;
        }
    }

    public function isFile(
        string $path,
    ): bool {
        try {
            $this->client->headObject([
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
            ]);

            return !str_ends_with($path, '/');
        } catch (S3Exception) {
            return false;
        }
    }

    public function isDirectory(
        string $path,
    ): bool {
        $prefix = rtrim($this->prefixPath($path), '/') . '/';

        $result = $this->client->listObjectsV2([
            'Bucket' => $this->config->bucket,
            'Prefix' => $prefix,
            'MaxKeys' => 1,
        ]);

        $keyCount = $result['KeyCount'] ?? 0;

        return $keyCount > 0;
    }

    /**
     * @throws FileNotFoundException|FilesystemException
     */
    public function info(
        string $path,
    ): FileInfo {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
            ]);

            $lastModified = $result['LastModified'];
            $timestamp = $lastModified instanceof DateTimeInterface
                ? $lastModified->getTimestamp()
                : 0;

            return new FileInfo(
                path: $path,
                size: (int) ($result['ContentLength'] ?? 0),
                lastModified: $timestamp,
                mimeType: (string) ($result['ContentType'] ?? 'application/octet-stream'),
                isDirectory: str_ends_with($path, '/'),
                visibility: 'private',
            );
        } catch (S3Exception $e) {
            if ($this->isNotFoundException($e)) {
                throw FileNotFoundException::forPath($path);
            }

            throw new FilesystemException(
                message: "Failed to get info for: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify the file exists and your AWS credentials are correct',
                previous: $e,
            );
        }
    }

    /**
     * @throws FileNotFoundException|FilesystemException
     */
    public function read(
        string $path,
    ): string {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
            ]);

            return (string) $result['Body'];
        } catch (S3Exception $e) {
            if ($this->isNotFoundException($e)) {
                throw FileNotFoundException::forPath($path);
            }

            throw new FilesystemException(
                message: "Failed to read file: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify the file exists and your AWS credentials are correct',
                previous: $e,
            );
        }
    }

    /**
     * @throws FileNotFoundException|FilesystemException
     */
    public function readStream(
        string $path,
    ): mixed {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
            ]);

            $body = $result['Body'];

            if (method_exists($body, 'detach')) {
                return $body->detach();
            }

            return $body;
        } catch (S3Exception $e) {
            if ($this->isNotFoundException($e)) {
                throw FileNotFoundException::forPath($path);
            }

            throw new FilesystemException(
                message: "Failed to read file stream: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify the file exists and your AWS credentials are correct',
                previous: $e,
            );
        }
    }

    /**
     * @throws FilesystemException
     */
    public function write(
        string $path,
        string $contents,
        array $options = [],
    ): bool {
        try {
            $params = [
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
                'Body' => $contents,
                'ContentType' => $options['content_type'] ?? $this->detectMimeType($path),
            ];

            if (isset($options['visibility'])) {
                $params['ACL'] = $this->visibilityToAcl($options['visibility']);
            }

            $this->client->putObject($params);

            return true;
        } catch (S3Exception $e) {
            throw new FilesystemException(
                message: "Failed to write file: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify your AWS credentials and bucket permissions',
                previous: $e,
            );
        }
    }

    /**
     * @throws FilesystemException
     */
    public function writeStream(
        string $path,
        mixed $resource,
        array $options = [],
    ): bool {
        if (!is_resource($resource)) {
            throw new FilesystemException(
                message: 'Invalid stream resource provided',
                context: 'Expected a valid stream resource',
                suggestion: 'Ensure the resource is a valid, open stream',
            );
        }

        try {
            $params = [
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
                'Body' => $resource,
                'ContentType' => $options['content_type'] ?? $this->detectMimeType($path),
            ];

            if (isset($options['visibility'])) {
                $params['ACL'] = $this->visibilityToAcl($options['visibility']);
            }

            $this->client->putObject($params);

            return true;
        } catch (S3Exception $e) {
            throw new FilesystemException(
                message: "Failed to write stream to file: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify your AWS credentials and bucket permissions',
                previous: $e,
            );
        }
    }

    /**
     * @throws FilesystemException
     */
    public function append(
        string $path,
        string $contents,
    ): bool {
        try {
            $existing = '';

            try {
                $existing = $this->read($path);
            } catch (FileNotFoundException) {
                // File doesn't exist yet, start fresh
            }

            return $this->write($path, $existing . $contents);
        } catch (FilesystemException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FilesystemException(
                message: "Failed to append to file: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify your AWS credentials and bucket permissions',
                previous: $e,
            );
        }
    }

    public function delete(
        string $path,
    ): bool {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
            ]);

            return true;
        } catch (S3Exception $e) {
            throw new FilesystemException(
                message: "Failed to delete file: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify your AWS credentials and bucket permissions',
                previous: $e,
            );
        }
    }

    /**
     * @throws FilesystemException
     */
    public function copy(
        string $source,
        string $destination,
    ): bool {
        try {
            $this->client->copyObject([
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($destination),
                'CopySource' => $this->config->bucket . '/' . $this->prefixPath($source),
            ]);

            return true;
        } catch (S3Exception $e) {
            throw new FilesystemException(
                message: "Failed to copy '$source' to '$destination'",
                context: $e->getMessage(),
                suggestion: 'Verify both paths are correct and your AWS credentials have the necessary permissions',
                previous: $e,
            );
        }
    }

    /**
     * @throws FilesystemException
     */
    public function move(
        string $source,
        string $destination,
    ): bool {
        $this->copy($source, $destination);
        $this->delete($source);

        return true;
    }

    /**
     * @throws FileNotFoundException|FilesystemException
     */
    public function size(
        string $path,
    ): int {
        return $this->info($path)->size;
    }

    /**
     * @throws FileNotFoundException|FilesystemException
     */
    public function lastModified(
        string $path,
    ): int {
        return $this->info($path)->lastModified;
    }

    /**
     * @throws FileNotFoundException|FilesystemException
     */
    public function mimeType(
        string $path,
    ): string {
        return $this->info($path)->mimeType;
    }

    public function listDirectory(
        string $path = '/',
    ): DirectoryListingInterface {
        try {
            $prefix = $this->prefixPath(rtrim($path, '/')) . '/';

            if ($prefix === '/') {
                $prefix = $this->config->prefix !== '' ? $this->config->prefix . '/' : '';
            }

            $result = $this->client->listObjectsV2([
                'Bucket' => $this->config->bucket,
                'Prefix' => $prefix,
                'Delimiter' => '/',
            ]);

            $entries = [];

            $contents = $result['Contents'] ?? [];

            foreach ($contents as $object) {
                $key = $this->stripPrefix((string) $object['Key']);

                // Skip the directory marker itself
                if ($key === rtrim($path, '/') . '/' || $key === '') {
                    continue;
                }

                $lastModified = $object['LastModified'] ?? null;
                $timestamp = $lastModified instanceof DateTimeInterface
                    ? $lastModified->getTimestamp()
                    : 0;

                $entries[] = new DirectoryEntry(
                    path: $key,
                    isDirectory: false,
                    size: (int) ($object['Size'] ?? 0),
                    lastModified: $timestamp,
                );
            }

            $commonPrefixes = $result['CommonPrefixes'] ?? [];

            foreach ($commonPrefixes as $prefix) {
                $dirPath = rtrim($this->stripPrefix((string) $prefix['Prefix']), '/');

                if ($dirPath === '') {
                    continue;
                }

                $entries[] = new DirectoryEntry(
                    path: $dirPath,
                    isDirectory: true,
                    size: 0,
                    lastModified: 0,
                );
            }

            return new DirectoryListing($entries);
        } catch (S3Exception $e) {
            throw new FilesystemException(
                message: "Failed to list directory: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify the path is correct and your AWS credentials have the necessary permissions',
                previous: $e,
            );
        }
    }

    public function makeDirectory(
        string $path,
    ): bool {
        try {
            $key = rtrim($this->prefixPath($path), '/') . '/';

            $this->client->putObject([
                'Bucket' => $this->config->bucket,
                'Key' => $key,
                'Body' => '',
                'ContentType' => 'application/x-directory',
            ]);

            return true;
        } catch (S3Exception $e) {
            throw new FilesystemException(
                message: "Failed to create directory: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify your AWS credentials and bucket permissions',
                previous: $e,
            );
        }
    }

    /**
     * @throws FilesystemException
     */
    public function deleteDirectory(
        string $path,
    ): bool {
        try {
            $prefix = rtrim($this->prefixPath($path), '/') . '/';

            $result = $this->client->listObjectsV2([
                'Bucket' => $this->config->bucket,
                'Prefix' => $prefix,
            ]);

            $objects = $result['Contents'] ?? [];

            if ($objects === []) {
                return true;
            }

            $deleteObjects = array_map(
                static fn (array $object): array => ['Key' => $object['Key']],
                $objects,
            );

            $this->client->deleteObjects([
                'Bucket' => $this->config->bucket,
                'Delete' => [
                    'Objects' => $deleteObjects,
                ],
            ]);

            return true;
        } catch (S3Exception $e) {
            throw new FilesystemException(
                message: "Failed to delete directory: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify your AWS credentials and bucket permissions',
                previous: $e,
            );
        }
    }

    /**
     * @throws FilesystemException
     */
    public function setVisibility(
        string $path,
        string $visibility,
    ): bool {
        try {
            $this->client->putObjectAcl([
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
                'ACL' => $this->visibilityToAcl($visibility),
            ]);

            return true;
        } catch (S3Exception $e) {
            throw new FilesystemException(
                message: "Failed to set visibility on: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify your AWS credentials and bucket permissions',
                previous: $e,
            );
        }
    }

    /**
     * @throws FilesystemException
     */
    public function visibility(
        string $path,
    ): string {
        try {
            $result = $this->client->getObjectAcl([
                'Bucket' => $this->config->bucket,
                'Key' => $this->prefixPath($path),
            ]);

            $grants = $result['Grants'] ?? [];

            foreach ($grants as $grant) {
                $grantee = $grant['Grantee'] ?? [];
                $permission = $grant['Permission'] ?? '';

                $uri = $grantee['URI'] ?? '';

                if ($uri === 'http://acs.amazonaws.com/groups/global/AllUsers' && $permission === 'READ') {
                    return 'public';
                }
            }

            return 'private';
        } catch (S3Exception $e) {
            throw new FilesystemException(
                message: "Failed to get visibility of: '$path'",
                context: $e->getMessage(),
                suggestion: 'Verify your AWS credentials and bucket permissions',
                previous: $e,
            );
        }
    }

    /**
     * Generate a public URL for the given path.
     */
    public function url(
        string $path,
    ): string {
        $prefixedPath = $this->prefixPath($path);

        if ($this->config->url !== null) {
            return rtrim($this->config->url, '/') . '/' . ltrim($prefixedPath, '/');
        }

        if ($this->config->endpoint !== null && $this->config->pathStyleEndpoint) {
            return rtrim($this->config->endpoint, '/')
                . '/' . $this->config->bucket
                . '/' . ltrim($prefixedPath, '/');
        }

        return 'https://' . $this->config->bucket
            . '.s3.' . $this->config->region
            . '.amazonaws.com/' . ltrim($prefixedPath, '/');
    }

    /**
     * Generate a temporary (pre-signed) URL for the given path.
     */
    public function temporaryUrl(
        string $path,
        int $expiration = 3600,
    ): string {
        $command = $this->client->getCommand('GetObject', [
            'Bucket' => $this->config->bucket,
            'Key' => $this->prefixPath($path),
        ]);

        $expiresAt = new DateTimeImmutable('+' . $expiration . ' seconds');

        /** @var RequestInterface $request */
        $request = $this->client->createPresignedRequest($command, $expiresAt);

        return (string) $request->getUri();
    }

    private function prefixPath(
        string $path,
    ): string {
        $normalized = ltrim($path, '/');

        if ($this->config->prefix === '') {
            return $normalized;
        }

        return $this->config->prefix . '/' . $normalized;
    }

    private function stripPrefix(
        string $key,
    ): string {
        if ($this->config->prefix === '') {
            return $key;
        }

        $prefix = $this->config->prefix . '/';

        if (str_starts_with($key, $prefix)) {
            return substr($key, strlen($prefix));
        }

        return $key;
    }

    private function isNotFoundException(
        S3Exception $e,
    ): bool {
        $code = $e->getAwsErrorCode();

        return $code === 'NoSuchKey'
            || $code === 'NotFound'
            || $code === '404'
            || $e->getStatusCode() === 404;
    }

    private function detectMimeType(
        string $path,
    ): string {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::MIME_TYPES[$extension] ?? 'application/octet-stream';
    }

    private function visibilityToAcl(
        string $visibility,
    ): string {
        return $visibility === 'public' ? 'public-read' : 'private';
    }
}

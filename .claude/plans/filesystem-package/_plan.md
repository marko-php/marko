# Plan: Filesystem Package (marko/filesystem + marko/filesystem-local)

## Created
2026-01-21

## Status
pending

## Objective
Implement the filesystem layer for Marko framework with a clean interface/implementation split pattern, providing `marko/filesystem` (interfaces, configuration, and file operations contracts) and `marko/filesystem-local` (local disk driver implementation).

## Scope

### In Scope
- `marko/filesystem` package with interfaces, value objects, and exceptions
  - `FilesystemInterface` - primary filesystem contract (read, write, delete, copy, move, exists, size, etc.)
  - `FileInfo` - value object encapsulating file metadata (path, size, type, modified time, visibility)
  - `DirectoryListing` - iterable directory contents with entries and subdirectories
  - `FilesystemConfig` - configuration loaded from `config/filesystem.php`
  - `FilesystemManager` - manages multiple named disk instances (default, public, temp)
  - `FilesystemException` hierarchy (FilesystemException, FileNotFoundException, PathException, PermissionException)
  - CLI commands: `storage:link` (create public storage symlink)
- `marko/filesystem-local` package with local disk driver implementation
  - `LocalFilesystem` - implements FilesystemInterface for local filesystem
  - `LocalFilesystemFactory` - factory for creating local filesystem instances with config
  - Path prefixing for disk isolation (e.g., `storage/uploads`, `public/assets`)
  - Visibility/permissions support (public read-only vs private)
  - Atomic operations for safety

### Out of Scope
- S3, cloud storage drivers (future packages: `marko/filesystem-s3`, `marko/filesystem-gcs`)
- FTP/SFTP drivers (future)
- Stream wrappers for remote access
- File locking mechanisms beyond OS-level
- Encryption at rest
- Disk quotas or usage tracking
- Directory watching/file system events

## Success Criteria
- [ ] `FilesystemInterface` provides clean contract for file operations
- [ ] `FileInfo` encapsulates file metadata
- [ ] `DirectoryListing` provides iterable directory contents
- [ ] `FilesystemConfig` loads configuration from `config/filesystem.php`
- [ ] `FilesystemManager` manages multiple named disks
- [ ] `LocalFilesystem` implements all operations using local filesystem
- [ ] `storage:link` creates symlink from `public/storage` to private `storage/public` directory
- [ ] Loud error when no filesystem driver is installed
- [ ] Driver conflict handling if multiple drivers installed
- [ ] Path isolation prevents directory traversal attacks
- [ ] Visibility control (public/private) enforced
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json files for both packages) | - | pending |
| 002 | FilesystemException hierarchy | 001 | pending |
| 003 | FileInfo value object | 001 | pending |
| 004 | DirectoryListing value object | 003 | pending |
| 005 | FilesystemInterface contract | 003, 004 | pending |
| 006 | FilesystemConfig class | 001 | pending |
| 007 | FilesystemManager | 005, 006 | pending |
| 008 | filesystem package module.php with bindings | 007 | pending |
| 009 | LocalFilesystem implementation | 005 | pending |
| 010 | LocalFilesystemFactory | 006, 009 | pending |
| 011 | filesystem-local module.php with bindings | 010 | pending |
| 012 | Path validation and security (prevents traversal) | 005 | pending |
| 013 | Visibility/permissions handling | 009 | pending |
| 014 | CLI: storage:link command | 007 | pending |
| 015 | Unit tests for filesystem package | 002, 003, 004, 005 | pending |
| 016 | Unit tests for filesystem-local package | 009, 010 | pending |
| 017 | Integration tests | 014 | pending |

## Architecture Notes

### Package Structure
```
packages/
  filesystem/                   # Interfaces + shared code
    src/
      Contracts/
        FilesystemInterface.php
        DirectoryListingInterface.php
      Config/
        FilesystemConfig.php
      Exceptions/
        FilesystemException.php
        FileNotFoundException.php
        PathException.php
        PermissionException.php
      Manager/
        FilesystemManager.php
      Values/
        FileInfo.php
        DirectoryListing.php
        DirectoryEntry.php
      Command/
        StorageLinkCommand.php
    tests/
    composer.json
    module.php
  filesystem-local/             # Local filesystem implementation
    src/
      Filesystem/
        LocalFilesystem.php
      Factory/
        LocalFilesystemFactory.php
    tests/
    composer.json
    module.php
```

### Config Location
```php
// config/filesystem.php
return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => 'storage',
            'public' => false,
        ],
        'public' => [
            'driver' => 'local',
            'path' => 'storage/public',
            'public' => true,
            'url' => '/storage',
        ],
        'temp' => [
            'driver' => 'local',
            'path' => 'storage/temp',
            'public' => false,
        ],
    ],
];
```

### FilesystemInterface Contract
```php
declare(strict_types=1);

namespace Marko\Filesystem\Contracts;

interface FilesystemInterface
{
    // File existence and info
    public function exists(string $path): bool;
    public function isFile(string $path): bool;
    public function isDirectory(string $path): bool;
    public function info(string $path): FileInfo;

    // Read operations
    public function read(string $path): string;
    public function readStream(string $path): mixed;

    // Write operations
    public function write(string $path, string $contents, array $options = []): bool;
    public function writeStream(string $path, mixed $resource, array $options = []): bool;
    public function append(string $path, string $contents): bool;

    // File operations
    public function delete(string $path): bool;
    public function copy(string $source, string $destination): bool;
    public function move(string $source, string $destination): bool;
    public function size(string $path): int;
    public function lastModified(string $path): int;
    public function mimeType(string $path): string;

    // Directory operations
    public function listDirectory(string $path = '/'): DirectoryListingInterface;
    public function makeDirectory(string $path): bool;
    public function deleteDirectory(string $path): bool;

    // Visibility
    public function setVisibility(string $path, string $visibility): bool;
    public function visibility(string $path): string;
}
```

### FileInfo Value Object
```php
declare(strict_types=1);

namespace Marko\Filesystem\Values;

readonly class FileInfo
{
    public function __construct(
        public string $path,
        public int $size,
        public int $lastModified,
        public string $mimeType,
        public bool $isDirectory,
        public string $visibility,
    ) {}
}
```

### DirectoryListingInterface
```php
declare(strict_types=1);

namespace Marko\Filesystem\Contracts;

use IteratorAggregate;

interface DirectoryListingInterface extends IteratorAggregate
{
    /** @return array<DirectoryEntry> */
    public function entries(): array;

    /** @return array<DirectoryEntry> */
    public function files(): array;

    /** @return array<DirectoryEntry> */
    public function directories(): array;
}
```

### FilesystemManager Implementation
```php
declare(strict_types=1);

namespace Marko\Filesystem\Manager;

use Marko\Filesystem\Config\FilesystemConfig;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FilesystemException;
use Psr\Container\ContainerInterface;

class FilesystemManager
{
    /** @var array<string, FilesystemInterface> */
    private array $disks = [];

    public function __construct(
        private readonly FilesystemConfig $config,
        private readonly ContainerInterface $container,
    ) {}

    public function disk(string $name = null): FilesystemInterface
    {
        $name ??= $this->config->getDefault();

        if (!isset($this->disks[$name])) {
            $diskConfig = $this->config->getDisk($name);
            $this->disks[$name] = $this->createDisk($diskConfig);
        }

        return $this->disks[$name];
    }

    private function createDisk(array $config): FilesystemInterface
    {
        $driver = $config['driver'] ?? throw new FilesystemException(
            message: 'Missing driver in disk config',
            context: json_encode($config),
            suggestion: 'Add a "driver" key to your disk configuration',
        );

        $factoryClass = match($driver) {
            'local' => LocalFilesystemFactory::class,
            default => throw new FilesystemException(
                message: "Unknown filesystem driver: {$driver}",
                context: "Available drivers depend on installed packages",
                suggestion: "For local storage: composer require marko/filesystem-local",
            ),
        };

        $factory = $this->container->get($factoryClass);
        return $factory->create($config);
    }
}
```

### Path Security (Prevents Directory Traversal)
```php
private function validatePath(string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    $normalized = ltrim($normalized, '/');

    if (str_contains($normalized, '../') || str_contains($normalized, '..')) {
        throw new PathException(
            message: "Path traversal attempt detected",
            context: "Attempted path: {$path}",
            suggestion: "Use paths relative to the disk root without '..' sequences",
        );
    }

    return $normalized;
}

private function fullPath(string $path): string
{
    $normalized = $this->validatePath($path);
    return $this->diskPath . '/' . $normalized;
}
```

### Atomic Write Operations
```php
public function write(string $path, string $contents, array $options = []): bool
{
    $fullPath = $this->fullPath($path);
    $tempPath = $fullPath . '.tmp.' . uniqid();

    try {
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_put_contents($tempPath, $contents, LOCK_EX) === false) {
            throw new FilesystemException(
                message: 'Failed to write temporary file',
                context: "Path: {$tempPath}",
                suggestion: 'Check write permissions for the storage directory',
            );
        }

        if (!rename($tempPath, $fullPath)) {
            @unlink($tempPath);
            throw new FilesystemException(
                message: 'Failed to move file to final location',
                context: "From: {$tempPath}, To: {$fullPath}",
                suggestion: 'Check write permissions for the target directory',
            );
        }

        if (isset($options['visibility'])) {
            $this->setVisibility($path, $options['visibility']);
        }

        return true;
    } catch (Throwable $e) {
        @unlink($tempPath);
        throw $e instanceof FilesystemException ? $e : FilesystemException::fromThrowable($e);
    }
}
```

### Storage Link Command
```bash
$ marko storage:link
Storage symlink created: public/storage -> ../storage/public
```

```php
declare(strict_types=1);

namespace Marko\Filesystem\Command;

use Marko\Cli\Attributes\Command;
use Marko\Cli\Contracts\OutputInterface;

#[Command(name: 'storage:link', description: 'Create public storage symlink')]
class StorageLinkCommand
{
    public function __invoke(OutputInterface $output): int
    {
        $publicPath = getcwd() . '/public/storage';
        $targetPath = '../storage/public';

        if (file_exists($publicPath)) {
            $output->error('Storage link already exists');
            return 1;
        }

        if (!symlink($targetPath, $publicPath)) {
            $output->error('Failed to create symlink');
            return 1;
        }

        $output->success("Storage symlink created: public/storage -> {$targetPath}");
        return 0;
    }
}
```

### Driver Conflict Handling
```
BindingConflictException: Multiple implementations bound for FilesystemInterface.

Context: Both LocalFilesystem and S3Filesystem are attempting to bind.

Suggestion: Install only one filesystem driver package. Remove one with:
  composer remove marko/filesystem-local
  or
  composer remove marko/filesystem-s3
```

### No Driver Installed Handling
```
FilesystemException: No filesystem driver installed.

Context: Attempted to resolve FilesystemInterface but no implementation is bound.

Suggestion: Install a filesystem driver package:
  composer require marko/filesystem-local
```

### Module Bindings

**filesystem/module.php**
```php
declare(strict_types=1);

use Marko\Filesystem\Config\FilesystemConfig;
use Marko\Filesystem\Manager\FilesystemManager;

return [
    'enabled' => true,
    'bindings' => [
        FilesystemConfig::class => FilesystemConfig::class,
        FilesystemManager::class => FilesystemManager::class,
    ],
];
```

**filesystem-local/module.php**
```php
declare(strict_types=1);

use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Manager\FilesystemManager;
use Marko\FilesystemLocal\Factory\LocalFilesystemFactory;

return [
    'enabled' => true,
    'bindings' => [
        LocalFilesystemFactory::class => LocalFilesystemFactory::class,
        FilesystemInterface::class => function (ContainerInterface $container): FilesystemInterface {
            return $container->get(FilesystemManager::class)->disk();
        },
    ],
];
```

### Usage Examples

**Multi-Disk Usage:**
```php
class DocumentStorage
{
    public function __construct(
        private FilesystemManager $fs,
    ) {}

    public function storePrivateDocument(string $filename, string $content): void
    {
        $this->fs->disk('local')->write("documents/{$filename}", $content);
    }

    public function storePublicDocument(string $filename, string $content): void
    {
        $this->fs->disk('public')->write("docs/{$filename}", $content);
    }

    public function getDocumentList(): DirectoryListingInterface
    {
        return $this->fs->disk('local')->listDirectory('documents');
    }
}
```

**Basic File Operations:**
```php
$fs = $container->get(FilesystemInterface::class);

// Write
$fs->write('uploads/document.txt', 'Hello World');

// Read
$content = $fs->read('uploads/document.txt');

// Check existence
if ($fs->exists('uploads/document.txt')) {
    $info = $fs->info('uploads/document.txt');
    echo "Size: {$info->size} bytes";
}

// Directory listing
foreach ($fs->listDirectory('uploads')->files() as $entry) {
    echo $entry->path;
}

// Delete
$fs->delete('uploads/document.txt');
```

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| **Directory traversal attacks** | Validate and normalize all paths, reject `..` and leading slashes |
| **Filesystem permissions errors** | Loud exceptions with clear suggestions for permission issues |
| **Concurrent writes** | Atomic operations using temp files and rename |
| **Large file handling** | Stream-based read/write methods for memory efficiency |
| **Symbolic link attacks** | Follow symlinks but validate target is within disk root |
| **Special characters in filenames** | Accept filesystem-valid names; sanitize if needed in application layer |
| **Missing storage directories** | Auto-create disk base directories on first use; fail loudly if not writable |
| **Public/private isolation** | Enforce via permissions at OS level; symlink strategy for serving |

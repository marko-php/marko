<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Database\Entity\EntityDiscovery;

/**
 * Helper to create a test entity file with unique namespace.
 */
function createUniqueEntityFile(
    string $path,
    string $baseNamespace,
    string $className,
    string $tableName,
    string $uniqueId,
): string {
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $namespace = $baseNamespace . '\\Test' . $uniqueId;
    $fullClassName = $namespace . '\\' . $className;

    $content = <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('$tableName')]
class $className extends Entity
{
    #[Column(primaryKey: true)]
    public int \$id;
}
PHP;

    file_put_contents($path, $content);

    return $fullClassName;
}

/**
 * Helper to recursively delete directory.
 */
function cleanupDir(
    string $path,
): void {
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $itemPath = $path . '/' . $item;

        if (is_dir($itemPath)) {
            cleanupDir($itemPath);
        } else {
            unlink($itemPath);
        }
    }

    rmdir($path);
}

beforeEach(function (): void {
    $this->discovery = new EntityDiscovery(new ClassFileParser());
    $this->uniqueId = uniqid();
    $this->tempDir = sys_get_temp_dir() . '/entity-discovery-' . $this->uniqueId;
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    cleanupDir($this->tempDir);
});

it('discovers all entity classes with #[Table] attribute across modules', function (): void {
    expect($this->discovery)->toBeInstanceOf(EntityDiscovery::class);
});

it('discovers entities in vendor path', function (): void {
    $expected = createUniqueEntityFile(
        $this->tempDir . '/vendor/acme/blog/src/Entity/Post.php',
        'Acme\Blog\Entity',
        'Post',
        'posts',
        $this->uniqueId,
    );

    $entities = $this->discovery->discoverInVendor($this->tempDir . '/vendor');

    expect($entities)
        ->toHaveCount(1)
        ->and($entities[0])->toBe($expected);
});

it('discovers entities in modules path', function (): void {
    $expected = createUniqueEntityFile(
        $this->tempDir . '/modules/acme/custom/src/Entity/Widget.php',
        'Acme\Custom\Entity',
        'Widget',
        'widgets',
        $this->uniqueId,
    );

    $entities = $this->discovery->discoverInModules($this->tempDir . '/modules');

    expect($entities)
        ->toHaveCount(1)
        ->and($entities[0])->toBe($expected);
});

it('discovers entities in app path', function (): void {
    $expected = createUniqueEntityFile(
        $this->tempDir . '/app/blog/Entity/Post.php',
        'App\Blog\Entity',
        'Post',
        'posts',
        $this->uniqueId,
    );

    $entities = $this->discovery->discoverInApp($this->tempDir . '/app');

    expect($entities)
        ->toHaveCount(1)
        ->and($entities[0])->toBe($expected);
});

it('discovers entities in a specific path', function (): void {
    $expected = createUniqueEntityFile(
        $this->tempDir . '/entity/User.php',
        'Test\Entity',
        'User',
        'users',
        $this->uniqueId,
    );

    $entities = $this->discovery->discoverInPath($this->tempDir . '/entity');

    expect($entities)
        ->toHaveCount(1)
        ->and($entities[0])->toBe($expected);
});

it('discovers multiple entities in same module', function (): void {
    $expected1 = createUniqueEntityFile(
        $this->tempDir . '/app/blog/Entity/Post.php',
        'App\Blog\Entity',
        'Post',
        'posts',
        $this->uniqueId,
    );

    $expected2 = createUniqueEntityFile(
        $this->tempDir . '/app/blog/Entity/Comment.php',
        'App\Blog\Entity',
        'Comment',
        'comments',
        $this->uniqueId,
    );

    $entities = $this->discovery->discoverInApp($this->tempDir . '/app');

    expect($entities)
        ->toHaveCount(2)
        ->toContain($expected1)
        ->toContain($expected2);
});

it('ignores classes without #[Table] attribute', function (): void {
    $path = $this->tempDir . '/app/blog/Entity/Service.php';
    mkdir(dirname($path), 0777, true);
    $namespace = 'App\Blog\Entity\Test' . $this->uniqueId;
    file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

class Service
{
    public int \$id;
}
PHP);

    $entities = $this->discovery->discoverInApp($this->tempDir . '/app');

    expect($entities)->toHaveCount(0);
});

it('ignores classes not extending Entity', function (): void {
    $path = $this->tempDir . '/app/blog/Entity/NotAnEntity.php';
    mkdir(dirname($path), 0777, true);
    $namespace = 'App\Blog\Entity\Test' . $this->uniqueId;
    file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Column;

#[Table('not_entities')]
class NotAnEntity
{
    #[Column]
    public int \$id;
}
PHP);

    $entities = $this->discovery->discoverInApp($this->tempDir . '/app');

    expect($entities)->toHaveCount(0);
});

it('returns empty array for non-existent path', function (): void {
    $entities = $this->discovery->discoverInPath('/non/existent/path');

    expect($entities)->toBe([]);
});

it('discovers all entities across all paths', function (): void {
    $expected1 = createUniqueEntityFile(
        $this->tempDir . '/vendor/marko/core/src/Entity/User.php',
        'Marko\Core\Entity',
        'User',
        'users',
        $this->uniqueId,
    );

    $expected2 = createUniqueEntityFile(
        $this->tempDir . '/modules/acme/mod/src/Entity/Widget.php',
        'Acme\Mod\Entity',
        'Widget',
        'widgets',
        $this->uniqueId,
    );

    $expected3 = createUniqueEntityFile(
        $this->tempDir . '/app/blog/Entity/Post.php',
        'App\Blog\Entity',
        'Post',
        'posts',
        $this->uniqueId,
    );

    $entities = $this->discovery->discoverAll(
        vendorPath: $this->tempDir . '/vendor',
        modulesPath: $this->tempDir . '/modules',
        appPath: $this->tempDir . '/app',
    );

    expect($entities)
        ->toHaveCount(3)
        ->toContain($expected1)
        ->toContain($expected2)
        ->toContain($expected3);
});

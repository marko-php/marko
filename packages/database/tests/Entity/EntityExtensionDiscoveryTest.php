<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Database\Entity\EntityExtensionDiscovery;
use Marko\Database\Exceptions\EntityException;

/**
 * Helper to create a unique entity file for discovery tests.
 */
function createDiscoveryEntityFile(
    string $path,
    string $namespace,
    string $className,
    string $tableName,
): void {
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

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
}

/**
 * Helper to create a unique extension file for discovery tests.
 */
function createDiscoveryExtensionFile(
    string $path,
    string $namespace,
    string $className,
    string $entityFqcn,
): string {
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $fullClassName = $namespace . '\\' . $className;

    $content = <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ExtensionOf;
use Marko\Database\Entity\EntityExtension;

#[ExtensionOf(entityClass: \\$entityFqcn::class)]
class $className extends EntityExtension
{
    #[Column]
    public string \$extra;
}
PHP;

    file_put_contents($path, $content);

    return $fullClassName;
}

/**
 * Helper to recursively delete directory.
 */
function cleanupDiscoveryDir(
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
            cleanupDiscoveryDir($itemPath);
        } else {
            unlink($itemPath);
        }
    }

    rmdir($path);
}

beforeEach(function (): void {
    $this->discovery = new EntityExtensionDiscovery(new ClassFileParser());
    $this->uniqueId = bin2hex(random_bytes(8));
    $this->tempDir = sys_get_temp_dir() . '/ext-discovery-' . $this->uniqueId;
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    cleanupDiscoveryDir($this->tempDir);
});

it('discovers extension classes in vendor src/EntityExtension directories', function (): void {
    $entityNs = 'DiscVendor' . $this->uniqueId . '\Blog\Entity';
    $entityClass = $entityNs . '\Post';
    $entityFile = $this->tempDir . '/vendor/acme/blog/src/Entity/Post.php';
    createDiscoveryEntityFile($entityFile, $entityNs, 'Post', 'posts');
    require_once $entityFile;

    $extNs = 'DiscVendor' . $this->uniqueId . '\Blog\EntityExtension';
    $extClass = createDiscoveryExtensionFile(
        $this->tempDir . '/vendor/acme/blog/src/EntityExtension/PostExtension.php',
        $extNs,
        'PostExtension',
        $entityClass,
    );

    $result = $this->discovery->discoverInVendor($this->tempDir . '/vendor');

    expect($result)->toHaveCount(1)
        ->and($result[0][0])->toBe($entityClass)
        ->and($result[0][1])->toBe($extClass);
});

it('discovers extension classes in app EntityExtension directories', function (): void {
    $entityNs = 'DiscApp' . $this->uniqueId . '\Blog\Entity';
    $entityClass = $entityNs . '\Post';
    $entityFile = $this->tempDir . '/app/blog/Entity/Post.php';
    createDiscoveryEntityFile($entityFile, $entityNs, 'Post', 'posts');
    require_once $entityFile;

    $extNs = 'DiscApp' . $this->uniqueId . '\Blog\EntityExtension';
    $extClass = createDiscoveryExtensionFile(
        $this->tempDir . '/app/blog/EntityExtension/PostExtension.php',
        $extNs,
        'PostExtension',
        $entityClass,
    );

    $result = $this->discovery->discoverInApp($this->tempDir . '/app');

    expect($result)->toHaveCount(1)
        ->and($result[0][0])->toBe($entityClass)
        ->and($result[0][1])->toBe($extClass);
});

it('skips classes that do not extend EntityExtension', function (): void {
    $entityNs = 'DiscSkipNoExt' . $this->uniqueId . '\Entity';
    $entityClass = $entityNs . '\Item';
    $entityFile = $this->tempDir . '/app/shop/Entity/Item.php';
    createDiscoveryEntityFile($entityFile, $entityNs, 'Item', 'items');
    require_once $entityFile;

    $extNs = 'DiscSkipNoExt' . $this->uniqueId . '\EntityExtension';
    $dir = $this->tempDir . '/app/shop/EntityExtension';
    mkdir($dir, 0777, true);
    $path = $dir . '/NotAnExtension.php';
    file_put_contents($path, <<<PHP
<?php
declare(strict_types=1);
namespace $extNs;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ExtensionOf;
use \\$entityClass;
#[ExtensionOf(entityClass: Item::class)]
class NotAnExtension {
    #[Column]
    public string \$extra;
}
PHP);

    $result = $this->discovery->discoverInApp($this->tempDir . '/app');

    expect($result)->toBeEmpty();
});

it('skips classes without ExtensionOf attribute', function (): void {
    $ns = 'DiscSkipNoAttr' . $this->uniqueId;
    $dir = $this->tempDir . '/app/blog/EntityExtension';
    mkdir($dir, 0777, true);
    $path = $dir . '/NoAttrExtension.php';
    file_put_contents($path, <<<PHP
<?php
declare(strict_types=1);
namespace $ns;
use Marko\Database\Attributes\Column;
use Marko\Database\Entity\EntityExtension;
class NoAttrExtension extends EntityExtension {
    #[Column]
    public string \$extra;
}
PHP);

    $result = $this->discovery->discoverInApp($this->tempDir . '/app');

    expect($result)->toBeEmpty();
});

it('throws when ExtensionOf target class does not extend Entity', function (): void {
    $notEntityNs = 'DiscThrows' . $this->uniqueId;
    $notEntityClass = $notEntityNs . '\NotAnEntity';
    $notEntityDir = $this->tempDir . '/app/bad/Other';
    mkdir($notEntityDir, 0777, true);
    $notEntityFile = $notEntityDir . '/NotAnEntity.php';
    file_put_contents($notEntityFile, <<<PHP
<?php
declare(strict_types=1);
namespace $notEntityNs;
class NotAnEntity {}
PHP);
    require_once $notEntityFile;

    $extNs = 'DiscThrows' . $this->uniqueId . '\EntityExtension';
    $dir = $this->tempDir . '/app/bad/EntityExtension';
    mkdir($dir, 0777, true);
    $path = $dir . '/BadExtension.php';
    file_put_contents($path, <<<PHP
<?php
declare(strict_types=1);
namespace $extNs;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ExtensionOf;
use Marko\Database\Entity\EntityExtension;
use \\$notEntityClass;
#[ExtensionOf(entityClass: NotAnEntity::class)]
class BadExtension extends EntityExtension {
    #[Column]
    public string \$extra;
}
PHP);

    $this->discovery->discoverInApp($this->tempDir . '/app');
})->throws(EntityException::class, 'does not extend Entity');

it('discovers extension classes in modules src/EntityExtension directories', function (): void {
    $entityNs = 'DiscModules' . $this->uniqueId . '\Custom\Entity';
    $entityClass = $entityNs . '\Widget';
    $entityFile = $this->tempDir . '/modules/acme/custom/src/Entity/Widget.php';
    createDiscoveryEntityFile($entityFile, $entityNs, 'Widget', 'widgets');
    require_once $entityFile;

    $extNs = 'DiscModules' . $this->uniqueId . '\Custom\EntityExtension';
    $extClass = createDiscoveryExtensionFile(
        $this->tempDir . '/modules/acme/custom/src/EntityExtension/WidgetExtension.php',
        $extNs,
        'WidgetExtension',
        $entityClass,
    );

    $result = $this->discovery->discoverInModules($this->tempDir . '/modules');

    expect($result)->toHaveCount(1)
        ->and($result[0][0])->toBe($entityClass)
        ->and($result[0][1])->toBe($extClass);
});

<?php

declare(strict_types=1);

use Marko\Database\Seed\SeederDefinition;
use Marko\Database\Seed\SeederDiscovery;

function createSeederFile(
    string $path,
    string $namespace,
    string $className,
    string $seederName,
    int $order = 0,
): void {
    $orderParam = $order !== 0 ? ", order: $order" : '';
    $seederCode = <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;

#[Seeder(name: '$seederName'$orderParam)]
class $className implements SeederInterface
{
    public function run(
        ConnectionInterface \$connection,
    ): void {
        // Seed data
    }
}
PHP;
    file_put_contents($path, $seederCode);
}

function cleanupDir(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }

    rmdir($dir);
}

describe('SeederDiscovery', function (): void {
    it('discovers seeders via #[Seeder] attribute', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        mkdir($tempDir . '/Seed', 0755, true);

        $seederCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestSeederModule;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;

#[Seeder(name: 'users')]
class UserSeeder implements SeederInterface
{
    public function run(
        ConnectionInterface $connection,
    ): void {
        // Seed users
    }
}
PHP;
        file_put_contents($tempDir . '/Seed/UserSeeder.php', $seederCode);

        try {
            $discovery = new SeederDiscovery();
            $seeders = $discovery->discoverInPath($tempDir . '/Seed');

            expect($seeders)->toHaveCount(1)
                ->and($seeders[0])->toBeInstanceOf(SeederDefinition::class)
                ->and($seeders[0]->seederClass)->toBe('TestSeederModule\\UserSeeder')
                ->and($seeders[0]->name)->toBe('users')
                ->and($seeders[0]->order)->toBe(0);
        } finally {
            unlink($tempDir . '/Seed/UserSeeder.php');
            rmdir($tempDir . '/Seed');
            rmdir($tempDir);
        }
    });

    it('ignores classes without #[Seeder] attribute', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        mkdir($tempDir . '/Seed', 0755, true);

        $classCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestSeederModule2;

class NotASeeder
{
    public function doSomething(): void {}
}
PHP;
        file_put_contents($tempDir . '/Seed/NotASeeder.php', $classCode);

        try {
            $discovery = new SeederDiscovery();
            $seeders = $discovery->discoverInPath($tempDir . '/Seed');

            expect($seeders)->toHaveCount(0);
        } finally {
            unlink($tempDir . '/Seed/NotASeeder.php');
            rmdir($tempDir . '/Seed');
            rmdir($tempDir);
        }
    });

    it('extracts order from attribute', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        mkdir($tempDir . '/Seed', 0755, true);

        $seederCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestSeederModule3;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;

#[Seeder(name: 'posts', order: 10)]
class PostSeeder implements SeederInterface
{
    public function run(
        ConnectionInterface $connection,
    ): void {
        // Seed posts
    }
}
PHP;
        file_put_contents($tempDir . '/Seed/PostSeeder.php', $seederCode);

        try {
            $discovery = new SeederDiscovery();
            $seeders = $discovery->discoverInPath($tempDir . '/Seed');

            expect($seeders)->toHaveCount(1)
                ->and($seeders[0]->name)->toBe('posts')
                ->and($seeders[0]->order)->toBe(10);
        } finally {
            unlink($tempDir . '/Seed/PostSeeder.php');
            rmdir($tempDir . '/Seed');
            rmdir($tempDir);
        }
    });

    it('discovers seeders in vendor/*/*/Seed/', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        $vendorPath = $tempDir . '/vendor/acme/blog/Seed';
        mkdir($vendorPath, 0755, true);

        createSeederFile(
            $vendorPath . '/VendorSeeder.php',
            'VendorAcmeBlog' . uniqid(),
            'VendorSeeder',
            'vendor-seeder',
        );

        try {
            $discovery = new SeederDiscovery();
            $seeders = $discovery->discoverInVendor($tempDir . '/vendor');

            expect($seeders)->toHaveCount(1)
                ->and($seeders[0]->name)->toBe('vendor-seeder');
        } finally {
            cleanupDir($tempDir);
        }
    });

    it('discovers seeders in modules/*/*/Seed/', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        $modulesPath = $tempDir . '/modules/acme/blog/Seed';
        mkdir($modulesPath, 0755, true);

        createSeederFile(
            $modulesPath . '/ModuleSeeder.php',
            'ModulesAcmeBlog' . uniqid(),
            'ModuleSeeder',
            'module-seeder',
        );

        try {
            $discovery = new SeederDiscovery();
            $seeders = $discovery->discoverInModules($tempDir . '/modules');

            expect($seeders)->toHaveCount(1)
                ->and($seeders[0]->name)->toBe('module-seeder');
        } finally {
            cleanupDir($tempDir);
        }
    });

    it('discovers seeders in app/*/Seed/', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        $appPath = $tempDir . '/app/blog/Seed';
        mkdir($appPath, 0755, true);

        createSeederFile(
            $appPath . '/AppSeeder.php',
            'AppBlog' . uniqid(),
            'AppSeeder',
            'app-seeder',
        );

        try {
            $discovery = new SeederDiscovery();
            $seeders = $discovery->discoverInApp($tempDir . '/app');

            expect($seeders)->toHaveCount(1)
                ->and($seeders[0]->name)->toBe('app-seeder');
        } finally {
            cleanupDir($tempDir);
        }
    });
});

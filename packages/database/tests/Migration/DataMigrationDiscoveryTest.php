<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Migration\DataMigrationDiscovery;
use Marko\Database\Tests\Migration\Helpers;

describe('DataMigrationDiscovery', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir() . '/marko_data_migration_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    });

    afterEach(function (): void {
        Helpers::removeDirectory($this->tempDir);
    });

    it('discovers data migrations in vendor/*/*/Data/', function (): void {
        // Create vendor structure
        $vendorPath = $this->tempDir . '/vendor/acme/blog/Data';
        mkdir($vendorPath, 0777, true);

        file_put_contents($vendorPath . '/001_insert_statuses.php', '<?php return new class {};');
        file_put_contents($vendorPath . '/002_insert_categories.php', '<?php return new class {};');

        $paths = new ProjectPaths($this->tempDir);
        $discovery = new DataMigrationDiscovery($paths);

        $migrations = $discovery->discover();

        expect($migrations)
            ->toHaveCount(2)
            ->and(array_column($migrations, 'name'))->toBe([
                '001_insert_statuses',
                '002_insert_categories',
            ]);
    });

    it('discovers data migrations in modules/*/*/Data/', function (): void {
        // Create modules structure
        $modulesPath = $this->tempDir . '/modules/acme/cms/Data';
        mkdir($modulesPath, 0777, true);

        file_put_contents($modulesPath . '/001_insert_pages.php', '<?php return new class {};');

        $paths = new ProjectPaths($this->tempDir);
        $discovery = new DataMigrationDiscovery($paths);

        $migrations = $discovery->discover();

        expect($migrations)
            ->toHaveCount(1)
            ->and($migrations[0]['name'])->toBe('001_insert_pages')
            ->and($migrations[0]['source'])->toBe('modules');
    });

    it('discovers data migrations in app/*/Data/', function (): void {
        // Create app structure
        $appPath = $this->tempDir . '/app/blog/Data';
        mkdir($appPath, 0777, true);

        file_put_contents($appPath . '/001_insert_custom_statuses.php', '<?php return new class {};');

        $paths = new ProjectPaths($this->tempDir);
        $discovery = new DataMigrationDiscovery($paths);

        $migrations = $discovery->discover();

        expect($migrations)
            ->toHaveCount(1)
            ->and($migrations[0]['name'])->toBe('001_insert_custom_statuses')
            ->and($migrations[0]['source'])->toBe('app');
    });

    it('applies data migrations in filename order', function (): void {
        // Create migrations across multiple sources
        $vendorPath = $this->tempDir . '/vendor/acme/blog/Data';
        $modulesPath = $this->tempDir . '/modules/custom/cms/Data';
        $appPath = $this->tempDir . '/app/blog/Data';

        mkdir($vendorPath, 0777, true);
        mkdir($modulesPath, 0777, true);
        mkdir($appPath, 0777, true);

        // Create in random order
        file_put_contents($appPath . '/003_app_data.php', '<?php return new class {};');
        file_put_contents($vendorPath . '/001_vendor_data.php', '<?php return new class {};');
        file_put_contents($modulesPath . '/002_modules_data.php', '<?php return new class {};');

        $paths = new ProjectPaths($this->tempDir);
        $discovery = new DataMigrationDiscovery($paths);

        $migrations = $discovery->discover();
        $names = array_column($migrations, 'name');

        // Should be sorted by filename across all sources
        expect($names)->toBe([
            '001_vendor_data',
            '002_modules_data',
            '003_app_data',
        ]);
    });

    it('returns empty array when no data migrations exist', function (): void {
        $paths = new ProjectPaths($this->tempDir);
        $discovery = new DataMigrationDiscovery($paths);

        $migrations = $discovery->discover();

        expect($migrations)->toBe([]);
    });

    it('includes full path in discovered migrations', function (): void {
        $vendorPath = $this->tempDir . '/vendor/acme/blog/Data';
        mkdir($vendorPath, 0777, true);

        file_put_contents($vendorPath . '/001_insert_data.php', '<?php return new class {};');

        $paths = new ProjectPaths($this->tempDir);
        $discovery = new DataMigrationDiscovery($paths);

        $migrations = $discovery->discover();

        expect($migrations[0]['path'])->toBe($vendorPath . '/001_insert_data.php');
    });
});

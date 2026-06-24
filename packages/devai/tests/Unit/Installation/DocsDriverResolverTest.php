<?php

declare(strict_types=1);

use Marko\DevAi\Installation\DocsDriverResolver;

beforeEach(function (): void {
    $this->tempRoot = devaiTempDir();
});

afterEach(function (): void {
    devaiRemoveDir($this->tempRoot);
});

/**
 * Write a stub known-drivers.php under {tempRoot}/vendor/marko/docs/.
 *
 * @param array<string, string> $drivers
 */
function writeKnownDrivers(string $tempRoot, array $drivers): void
{
    $docsDir = $tempRoot . '/vendor/marko/docs';
    mkdir($docsDir, 0755, true);
    $export = var_export($drivers, true);
    file_put_contents($docsDir . '/known-drivers.php', "<?php\nreturn $export;\n");
}

/** Create a fake vendor directory for the given package (e.g. 'marko/docs-fts'). */
function makeVendorDir(string $tempRoot, string $package): void
{
    mkdir($tempRoot . '/vendor/' . $package, 0755, true);
}

describe('DocsDriverResolver', function (): void {
    it('returns the installed driver when its vendor directory exists', function (): void {
        writeKnownDrivers($this->tempRoot, [
            'marko/docs-fts' => 'Full-text documentation search driver (recommended; SQLite FTS5, zero infrastructure)',
        ]);
        makeVendorDir($this->tempRoot, 'marko/docs-fts');

        $resolver = new DocsDriverResolver();

        expect($resolver->installedDriver($this->tempRoot))->toBe('marko/docs-fts');
    });

    it('returns null for installed driver when no known driver is present', function (): void {
        writeKnownDrivers($this->tempRoot, [
            'marko/docs-fts' => 'Full-text documentation search driver (recommended; SQLite FTS5, zero infrastructure)',
        ]);
        // No vendor dir created for any known driver

        $resolver = new DocsDriverResolver();

        expect($resolver->installedDriver($this->tempRoot))->toBeNull();
    });

    it('lists known drivers that are not installed', function (): void {
        writeKnownDrivers($this->tempRoot, [
            'marko/docs-fts' => 'Full-text documentation search driver (recommended; SQLite FTS5, zero infrastructure)',
            'marko/docs-vec' => 'Vector search driver',
        ]);
        makeVendorDir($this->tempRoot, 'marko/docs-vec');

        $resolver = new DocsDriverResolver();

        expect($resolver->uninstalledDrivers($this->tempRoot))->toBe(['marko/docs-fts']);
    });

    it('picks the recommended uninstalled driver by the description convention', function (): void {
        writeKnownDrivers($this->tempRoot, [
            'marko/docs-vec' => 'Vector search driver',
            'marko/docs-fts' => 'Full-text documentation search driver (recommended; SQLite FTS5, zero infrastructure)',
        ]);
        // Neither installed — recommended one should be picked

        $resolver = new DocsDriverResolver();

        expect($resolver->recommendedUninstalled($this->tempRoot))->toBe('marko/docs-fts');
    });

    it('derives the build command from the package name', function (): void {
        $resolver = new DocsDriverResolver();

        expect($resolver->buildCommand('marko/docs-fts'))->toBe('docs-fts:build');
    });

    it('treats the known set as empty when the contract registry file is absent', function (): void {
        // No vendor/marko/docs/known-drivers.php written

        $resolver = new DocsDriverResolver();

        expect($resolver->installedDriver($this->tempRoot))->toBeNull()
            ->and($resolver->uninstalledDrivers($this->tempRoot))->toBeEmpty()
            ->and($resolver->recommendedUninstalled($this->tempRoot))->toBeNull();
    });
});

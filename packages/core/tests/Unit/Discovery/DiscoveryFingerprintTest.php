<?php

declare(strict_types=1);

use Marko\Core\Discovery\DiscoveryFingerprint;
use Marko\Core\Path\ProjectPaths;

function fingerprintProject(): string
{
    $base = sys_get_temp_dir() . '/marko-fingerprint-' . bin2hex(random_bytes(6));
    mkdir($base . '/vendor/composer', 0755, true);
    mkdir($base . '/app/blog', 0755, true);
    mkdir($base . '/modules/acme/shop', 0755, true);
    file_put_contents($base . '/vendor/composer/installed.json', '{"packages": []}');
    file_put_contents($base . '/app/blog/composer.json', '{"name": "app/blog"}');
    file_put_contents($base . '/modules/acme/shop/composer.json', '{"name": "acme/shop"}');

    return $base;
}

function fingerprintCleanup(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

describe('DiscoveryFingerprint', function (): void {
    beforeEach(function (): void {
        $this->base = fingerprintProject();
        $this->paths = new ProjectPaths($this->base);
    });

    afterEach(function (): void {
        fingerprintCleanup($this->base);
    });

    it('computes the same fingerprint for an unchanged project', function (): void {
        $fingerprint = new DiscoveryFingerprint();

        expect($fingerprint->compute($this->paths))->toBe($fingerprint->compute($this->paths));
    });

    it('changes when installed.json changes', function (): void {
        $fingerprint = new DiscoveryFingerprint();
        $before = $fingerprint->compute($this->paths);

        file_put_contents($this->base . '/vendor/composer/installed.json', '{"packages": [{"name": "acme/new"}]}');

        expect($fingerprint->compute($this->paths))->not->toBe($before);
    });

    it('changes when a module directory is added under app or modules', function (): void {
        $fingerprint = new DiscoveryFingerprint();
        $before = $fingerprint->compute($this->paths);

        mkdir($this->base . '/app/admin');
        file_put_contents($this->base . '/app/admin/composer.json', '{}');
        $afterApp = $fingerprint->compute($this->paths);

        mkdir($this->base . '/modules/acme/billing');
        file_put_contents($this->base . '/modules/acme/billing/composer.json', '{}');

        expect($afterApp)->not->toBe($before)
            ->and($fingerprint->compute($this->paths))->not->toBe($afterApp);
    });

    it('changes when an app or modules composer.json changes', function (): void {
        $fingerprint = new DiscoveryFingerprint();
        $before = $fingerprint->compute($this->paths);

        file_put_contents($this->base . '/app/blog/composer.json', '{"name": "app/blog", "autoload": {}}');
        $afterApp = $fingerprint->compute($this->paths);

        file_put_contents($this->base . '/modules/acme/shop/composer.json', '{"name": "acme/shop", "version": "2"}');

        expect($afterApp)->not->toBe($before)
            ->and($fingerprint->compute($this->paths))->not->toBe($afterApp);
    });

    it('computes the same fingerprint without parsing any composer.json', function (): void {
        // Contents are hashed, never decoded, so invalid JSON is not an error.
        file_put_contents($this->base . '/app/blog/composer.json', 'not json at all');
        $fingerprint = new DiscoveryFingerprint();

        expect($fingerprint->compute($this->paths))->toBe($fingerprint->compute($this->paths));
    });

    it('does not descend into a module directory under modules', function (): void {
        $fingerprint = new DiscoveryFingerprint();
        $before = $fingerprint->compute($this->paths);

        mkdir($this->base . '/modules/acme/shop/nested');
        file_put_contents($this->base . '/modules/acme/shop/nested/composer.json', '{}');

        expect($fingerprint->compute($this->paths))->toBe($before);
    });

    it('changes when a root config file is edited, added or removed', function (): void {
        mkdir($this->base . '/config');
        file_put_contents($this->base . '/config/app.php', "<?php return ['name' => 'a'];");
        $fingerprint = new DiscoveryFingerprint();
        $before = $fingerprint->compute($this->paths);

        file_put_contents($this->base . '/config/app.php', "<?php return ['name' => 'b'];");
        $afterEdit = $fingerprint->compute($this->paths);

        file_put_contents($this->base . '/config/mail.php', '<?php return [];');
        $afterAdd = $fingerprint->compute($this->paths);

        unlink($this->base . '/config/mail.php');

        expect($afterEdit)->not->toBe($before)
            ->and($afterAdd)->not->toBe($afterEdit)
            ->and($fingerprint->compute($this->paths))->toBe($afterEdit);
    })->issue(556);

    it('ignores files in the root config directory that are not PHP files', function (): void {
        mkdir($this->base . '/config');
        $fingerprint = new DiscoveryFingerprint();
        $before = $fingerprint->compute($this->paths);

        file_put_contents($this->base . '/config/notes.txt', 'not config');

        expect($fingerprint->compute($this->paths))->toBe($before);
    })->issue(556);

    it('computes a fingerprint for a project without installed.json, app or modules', function (): void {
        $paths = new ProjectPaths(sys_get_temp_dir() . '/marko-fingerprint-missing-' . bin2hex(random_bytes(6)));

        expect(new DiscoveryFingerprint()->compute($paths))->toBeString()->not->toBe('');
    });
});

<?php

declare(strict_types=1);

use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Module\CachedModule;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleDiscovery;

function t002ModuleDir(
    ?string $modulePhp = null,
): string {
    $dir = sys_get_temp_dir() . '/marko-t002-arguments-' . bin2hex(random_bytes(6));
    mkdir($dir, 0755, true);
    file_put_contents($dir . '/composer.json', json_encode([
        'name' => 'acme/blog',
        'version' => '1.0.0',
        'extra' => ['marko' => ['module' => true]],
    ]));

    if ($modulePhp !== null) {
        file_put_contents($dir . '/module.php', $modulePhp);
    }

    return $dir;
}

function t002Cleanup(
    string $dir,
): void {
    array_map('unlink', glob($dir . '/*') ?: []);
    rmdir($dir);
}

describe('ModuleManifest arguments', function (): void {
    it('defaults arguments to an empty array when module.php is missing', function (): void {
        $dir = t002ModuleDir();

        $manifest = (new ManifestParser())->parse($dir);
        t002Cleanup($dir);

        expect($manifest->arguments)->toBeArray()->toBeEmpty();
    });

    it('defaults arguments to an empty array when module.php has no arguments key', function (): void {
        $dir = t002ModuleDir("<?php return ['enabled' => true];");

        $manifest = (new ManifestParser())->parse($dir);
        t002Cleanup($dir);

        expect($manifest->arguments)->toBeArray()->toBeEmpty();
    });

    it('reads arguments keyed by class and parameter name', function (): void {
        $dir = t002ModuleDir(
            "<?php return ['arguments' => ['Acme\\\\Blog\\\\Mailer' => ['host' => 'smtp.local', 'port' => 25]]];",
        );

        $manifest = (new ManifestParser())->parse($dir);
        t002Cleanup($dir);

        expect($manifest->arguments)->toBe(['Acme\\Blog\\Mailer' => ['host' => 'smtp.local', 'port' => 25]]);
    });

    it('keeps closure values as live closures', function (): void {
        $dir = t002ModuleDir(
            "<?php return ['arguments' => ['Acme\\\\Blog\\\\Mailer' => ['host' => fn () => 'built']]];",
        );

        $manifest = (new ManifestParser())->parse($dir);
        t002Cleanup($dir);

        expect($manifest->arguments['Acme\\Blog\\Mailer']['host'])->toBeInstanceOf(Closure::class)
            ->and(($manifest->arguments['Acme\\Blog\\Mailer']['host'])())->toBe('built');
    });

    it('reads arguments when rebuilding a manifest from the discovery cache', function (): void {
        $dir = t002ModuleDir("<?php return ['arguments' => ['Acme\\\\Blog\\\\Mailer' => ['host' => 'smtp.local']]];");

        $manifest = (new ManifestParser())->parseCached(new CachedModule(
            name: 'acme/blog',
            version: '1.0.0',
            path: $dir,
            source: 'app',
        ));
        t002Cleanup($dir);

        expect($manifest->arguments)->toBe(['Acme\\Blog\\Mailer' => ['host' => 'smtp.local']]);
    });

    it('keeps arguments on manifests found by module discovery', function (): void {
        $app = sys_get_temp_dir() . '/marko-t002-app-' . bin2hex(random_bytes(6));
        mkdir($app);
        $dir = t002ModuleDir("<?php return ['arguments' => ['Acme\\\\Blog\\\\Mailer' => ['host' => 'smtp.local']]];");
        rename($dir, $app . '/blog');

        $modules = (new ModuleDiscovery(new ManifestParser()))->discoverInApp($app);
        t002Cleanup($app . '/blog');
        rmdir($app);

        expect($modules)->toHaveCount(1)
            ->and($modules[0]->arguments)->toBe(['Acme\\Blog\\Mailer' => ['host' => 'smtp.local']]);
    });

    it('throws when arguments is not an array', function (): void {
        $dir = t002ModuleDir("<?php return ['arguments' => 'nope'];");

        try {
            (new ManifestParser())->parse($dir);
            $thrown = null;
        } catch (ArgumentException $e) {
            $thrown = $e;
        } finally {
            t002Cleanup($dir);
        }

        expect($thrown)->toBeInstanceOf(ArgumentException::class)
            ->and($thrown->getMessage())
            ->toContain('acme/blog')
            ->toContain('arguments');
    });

    it('throws when a class entry is not an array keyed by parameter name', function (string $arguments): void {
        $dir = t002ModuleDir("<?php return ['arguments' => $arguments];");

        try {
            (new ManifestParser())->parse($dir);
            $thrown = null;
        } catch (ArgumentException $e) {
            $thrown = $e;
        } finally {
            t002Cleanup($dir);
        }

        expect($thrown)->toBeInstanceOf(ArgumentException::class)
            ->and($thrown->getMessage())->toContain('acme/blog');
    })->with([
        'value is a string' => ["['Acme\\\\Mailer' => 'smtp']"],
        'parameter key is an int' => ["['Acme\\\\Mailer' => ['smtp']]"],
        'parameter key is empty' => ["['Acme\\\\Mailer' => ['' => 'smtp']]"],
        'class key is empty' => ["['' => ['host' => 'smtp']]"],
        'class key is an int' => ["[['host' => 'smtp']]"],
    ]);
});

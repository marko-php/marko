<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Marko\Roadrunner\Worker\BasePathResolver;

describe('BasePathResolver', function (): void {
    it('resolves the application base path through a symlinked vendor directory', function (): void {
        $previousEnv = getenv('MARKO_BASE_PATH');
        putenv(
            'MARKO_BASE_PATH',
        ); // force the env source to miss, so resolution falls through to the autoloader source

        $projectRoot = sys_get_temp_dir() . '/marko-base-path-test-' . bin2hex(random_bytes(8));
        $packageSource = sys_get_temp_dir() . '/marko-base-path-source-' . bin2hex(random_bytes(8));

        // A downstream app's real, non-symlinked project structure.
        mkdir($projectRoot . '/vendor/composer', 0755, true);
        mkdir($projectRoot . '/app', 0755, true);
        mkdir($projectRoot . '/modules', 0755, true);
        file_put_contents($projectRoot . '/vendor/composer/ClassLoader.php', '<?php');

        // The monorepo package source that vendor/marko/roadrunner symlinks
        // to under a Composer path repository. Deliberately has none of
        // vendor/, app/, modules/ — resolving into this tree instead of
        // $projectRoot must not happen.
        mkdir($packageSource, 0755, true);
        symlink($packageSource, $projectRoot . '/vendor/marko-roadrunner-symlink');

        $locator = new FakeComposerAutoloaderLocator($projectRoot . '/vendor/composer/ClassLoader.php');
        $resolver = new BasePathResolver($locator);

        try {
            $resolved = $resolver->resolve();

            expect($resolved)->toBe($projectRoot);
        } finally {
            unlink($projectRoot . '/vendor/marko-roadrunner-symlink');
            unlink($projectRoot . '/vendor/composer/ClassLoader.php');
            rmdir($projectRoot . '/vendor/composer');
            rmdir($projectRoot . '/vendor');
            rmdir($projectRoot . '/app');
            rmdir($projectRoot . '/modules');
            rmdir($projectRoot);
            rmdir($packageSource);
            $previousEnv === false ? putenv('MARKO_BASE_PATH') : putenv("MARKO_BASE_PATH=$previousEnv");
        }
    });
});

<?php

declare(strict_types=1);

it(
    'has a valid composer.json with correct package name marko/core',
    function () {
        $composerPath = dirname(
            __DIR__,
            2
        ) . '/composer.json';
    
        expect(
            file_exists(
                $composerPath
            )
        )->toBeTrue(
            'composer.json should exist'
        );
    
        $content = file_get_contents(
            $composerPath
        );
        $composer = json_decode(
            $content,
            true
        );
    
        expect(
            json_last_error()
        )->toBe(
            JSON_ERROR_NONE,
            'composer.json should be valid JSON'
        );
        expect(
            $composer['name']
        )->toBe(
            'marko/core'
        );
    }
);

it(
    'has PSR-4 autoloading configured for Marko\Core namespace',
    function () {
        $composerPath = dirname(
            __DIR__,
            2
        ) . '/composer.json';
        $composer = json_decode(
            file_get_contents(
                $composerPath
            ),
            true
        );
    
        expect(
            $composer
        )->toHaveKey(
            'autoload'
        );
        expect(
            $composer['autoload']
        )->toHaveKey(
            'psr-4'
        );
        expect(
            $composer['autoload']['psr-4']
        )->toHaveKey(
            'Marko\\Core\\'
        );
        expect(
            $composer['autoload']['psr-4']['Marko\\Core\\']
        )->toBe(
            'src/'
        );
    }
);

it(
    'has a module.php manifest with name marko/core',
    function () {
        $modulePath = dirname(
            __DIR__,
            2
        ) . '/module.php';
    
        expect(
            file_exists(
                $modulePath
            )
        )->toBeTrue(
            'module.php should exist'
        );
    
        $manifest = require $modulePath;
    
        expect(
            $manifest
        )->toBeArray();
        expect(
            $manifest
        )->toHaveKey(
            'name'
        );
        expect(
            $manifest['name']
        )->toBe(
            'marko/core'
        );
    }
);

it(
    'has src directory for source code',
    function () {
        $srcPath = dirname(
            __DIR__,
            2
        ) . '/src';
    
        expect(
            is_dir(
                $srcPath
            )
        )->toBeTrue(
            'src directory should exist'
        );
    }
);

it(
    'has tests/Unit directory for unit tests',
    function () {
        $unitTestsPath = dirname(
            __DIR__,
            2
        ) . '/tests/Unit';
    
        expect(
            is_dir(
                $unitTestsPath
            )
        )->toBeTrue(
            'tests/Unit directory should exist'
        );
    }
);

it(
    'has tests/Feature directory for feature tests',
    function () {
        $featureTestsPath = dirname(
            __DIR__,
            2
        ) . '/tests/Feature';
    
        expect(
            is_dir(
                $featureTestsPath
            )
        )->toBeTrue(
            'tests/Feature directory should exist'
        );
    }
);

it(
    'requires PHP 8.5 or higher',
    function () {
        $composerPath = dirname(
            __DIR__,
            2
        ) . '/composer.json';
        $composer = json_decode(
            file_get_contents(
                $composerPath
            ),
            true
        );
    
        expect(
            $composer
        )->toHaveKey(
            'require'
        );
        expect(
            $composer['require']
        )->toHaveKey(
            'php'
        );
        expect(
            $composer['require']['php']
        )->toBe(
            '^8.5'
        );
    }
);

it(
    'requires psr/container for PSR-11 ContainerInterface',
    function () {
        $composerPath = dirname(
            __DIR__,
            2
        ) . '/composer.json';
        $composer = json_decode(
            file_get_contents(
                $composerPath
            ),
            true
        );
    
        expect(
            $composer
        )->toHaveKey(
            'require'
        );
        expect(
            $composer['require']
        )->toHaveKey(
            'psr/container'
        );
    }
);

it(
    'requires pestphp/pest as dev dependency for testing',
    function () {
        $composerPath = dirname(
            __DIR__,
            2
        ) . '/composer.json';
        $composer = json_decode(
            file_get_contents(
                $composerPath
            ),
            true
        );
    
        expect(
            $composer
        )->toHaveKey(
            'require-dev'
        );
        expect(
            $composer['require-dev']
        )->toHaveKey(
            'pestphp/pest'
        );
    }
);

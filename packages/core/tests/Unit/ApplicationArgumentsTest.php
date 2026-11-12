<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Exceptions\ArgumentConflictException;
use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Tests\Unit\T007AbstractMailer;
use Marko\Core\Tests\Unit\T007Mailer;

/**
 * Write a throwaway project whose modules live under vendor/, modules/ and app/.
 *
 * @param array<string, string> $modules Relative module directory (e.g. "vendor/acme/mail") => module.php body
 */
function argumentsTestProject(
    array $modules,
): string {
    $base = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));

    foreach ($modules as $directory => $moduleBody) {
        mkdir("$base/$directory", 0755, true);
        file_put_contents("$base/$directory/composer.json", json_encode([
            'name' => preg_replace('#^(vendor|modules|app)/#', '', $directory, 1) ?: $directory,
            'extra' => ['marko' => ['module' => true]],
        ]));

        if ($moduleBody !== '') {
            file_put_contents("$base/$directory/module.php", "<?php\n\ndeclare(strict_types=1);\n\n$moduleBody\n");
        }
    }

    foreach (['vendor', 'modules', 'app'] as $source) {
        is_dir("$base/$source") || mkdir("$base/$source", 0755, true);
    }

    return $base;
}

function argumentsTestApplication(
    string $base,
): Application {
    return new Application(vendorPath: "$base/vendor", modulesPath: "$base/modules", appPath: "$base/app");
}

function argumentsTestCleanup(
    string $dir,
): void {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

it('builds a class with arguments declared in a module\'s module.php on a live boot', function (): void {
    $class = T007Mailer::class;
    $base = argumentsTestProject([
        'vendor/acme/mail' => "return ['arguments' => [\\$class::class => ['host' => 'smtp.acme.test', 'port' => 587]]];",
    ]);

    $app = argumentsTestApplication($base);
    $app->initialize(false);
    $mailer = $app->container->get(T007Mailer::class);

    argumentsTestCleanup($base);

    expect($mailer->host)->toBe('smtp.acme.test')
        ->and($mailer->port)->toBe(587);
});

it('lets an app module override one argument declared by a vendor module', function (): void {
    $class = T007Mailer::class;
    $base = argumentsTestProject([
        'vendor/acme/mail' => "return ['arguments' => [\\$class::class => ['host' => 'smtp.acme.test', 'port' => 587]]];",
        'app/site' => "return ['arguments' => [\\$class::class => ['port' => 2525]]];",
    ]);

    $app = argumentsTestApplication($base);
    $app->initialize(false);
    $mailer = $app->container->get(T007Mailer::class);

    argumentsTestCleanup($base);

    expect($mailer->host)->toBe('smtp.acme.test')
        ->and($mailer->port)->toBe(2525);
});

it('fails the boot when a module declares an argument for an unknown parameter', function (): void {
    $class = T007Mailer::class;
    $base = argumentsTestProject([
        'vendor/acme/mail' => "return ['arguments' => [\\$class::class => ['hots' => 'smtp.acme.test']]];",
    ]);

    $app = argumentsTestApplication($base);

    try {
        expect(fn () => $app->initialize(false))->toThrow(ArgumentException::class, 'hots');
    } finally {
        $app->bootstrapErrorHandler->unregister();
        argumentsTestCleanup($base);
    }
});

it('fails the boot when two same-priority modules set the same argument', function (): void {
    $class = T007Mailer::class;
    $base = argumentsTestProject([
        'vendor/acme/mail' => "return ['arguments' => [\\$class::class => ['host' => 'a.test']]];",
        'vendor/other/mail' => "return ['arguments' => [\\$class::class => ['host' => 'b.test']]];",
    ]);

    $app = argumentsTestApplication($base);

    try {
        expect(fn () => $app->initialize(false))->toThrow(ArgumentConflictException::class, 'host');
    } finally {
        $app->bootstrapErrorHandler->unregister();
        argumentsTestCleanup($base);
    }
});

it('fails the boot when a boot callback closure-binds a class that has arguments', function (): void {
    $class = T007Mailer::class;
    $base = argumentsTestProject([
        'vendor/acme/mail' => <<<PHP
            return [
                'arguments' => [\\$class::class => ['host' => 'a.test']],
                'boot' => function (\\Marko\\Core\\Container\\ContainerInterface \$container): void {
                    \$container->bind(\\$class::class, fn () => new \\$class());
                },
            ];
            PHP,
    ]);

    $app = argumentsTestApplication($base);

    try {
        expect(fn () => $app->initialize(false))->toThrow(ArgumentException::class, 'closure');
    } finally {
        $app->bootstrapErrorHandler->unregister();
        argumentsTestCleanup($base);
    }
});

it(
    'lets an app module take over a class with a closure binding, dropping the vendor module\'s arguments',
    function (): void {
        $class = T007Mailer::class;
        $base = argumentsTestProject([
            'vendor/acme/mail' => "return ['arguments' => [\\$class::class => ['host' => 'vendor.test']]];",
            'app/site' => "return ['bindings' => [\\$class::class => fn () => new \\$class('app.test', 1)]];",
        ]);

        $app = argumentsTestApplication($base);
        $app->initialize(false);
        $mailer = $app->container->get(T007Mailer::class);

        argumentsTestCleanup($base);

        expect($mailer->host)->toBe('app.test')
            ->and($mailer->port)->toBe(1);
    },
);

it('fails the boot when arguments are keyed by an abstract class with no preference replacement', function (): void {
    $class = T007AbstractMailer::class;
    $base = argumentsTestProject([
        'vendor/acme/mail' => "return ['arguments' => [\\$class::class => ['host' => 'a.test']]];",
    ]);

    $app = argumentsTestApplication($base);

    try {
        expect(fn () => $app->initialize(false))->toThrow(ArgumentException::class, 'abstract');
    } finally {
        $app->bootstrapErrorHandler->unregister();
        argumentsTestCleanup($base);
    }
});

it('boots a module that has no module.php', function (): void {
    $base = argumentsTestProject(['vendor/acme/plain' => '']);

    $app = argumentsTestApplication($base);
    $app->initialize(false);
    $names = array_map(fn ($module): string => $module->name, $app->modules);

    argumentsTestCleanup($base);

    expect($names)->toBe(['acme/plain']);
});

it('applies arguments on a boot from the discovery cache', function (): void {
    $saved = [$_ENV['APP_ENV'] ?? null];
    $_ENV['APP_ENV'] = 'production';
    $class = T007Mailer::class;
    $base = argumentsTestProject([
        'vendor/acme/mail' => "return ['arguments' => [\\$class::class => ['host' => 'cached.test', 'port' => 465]]];",
    ]);

    try {
        $live = argumentsTestApplication($base);
        $live->initialize(false);
        $payload = new DiscoveryCompiler($live->container)->compile($live->modules);
        $payload['sections']['routes'] = [];
        $payload['sections']['http_exception_responders'] = [];
        $live->container->get(DiscoveryCache::class)->write($payload);

        $cached = argumentsTestApplication($base);
        $cached->initialize();
        $mailer = $cached->container->get(T007Mailer::class);
    } finally {
        unset($_ENV['APP_ENV']);

        if ($saved[0] !== null) {
            $_ENV['APP_ENV'] = $saved[0];
        }

        argumentsTestCleanup($base);
    }

    expect($mailer->host)->toBe('cached.test')
        ->and($mailer->port)->toBe(465);
});

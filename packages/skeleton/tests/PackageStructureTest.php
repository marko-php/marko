<?php

declare(strict_types=1);

it('has a valid composer.json with type project', function (): void {
    $composerPath = __DIR__ . '/../composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['name'])->toBe('marko/skeleton')
        ->and($composer['type'])->toBe('project');
});

it('requires marko/framework', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require'])->toHaveKey('marko/framework');
});

it('requires marko/env', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require'])->toHaveKey('marko/env');
});

it('requires marko/devserver as a dev dependency', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require-dev'])->toHaveKey('marko/devserver');
});

it('requires marko/testing as a dev dependency', function (): void {
    // The testing package (fakes + Pest expectations) is dev-only tooling and
    // must live in require-dev of the skeleton (the generated app's root), not
    // in require — a dependency's require-dev is never installed transitively,
    // so it cannot be delivered via marko/framework. See require check below.
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require-dev'])->toHaveKey('marko/testing');
});

it('does not add marko/testing to require (dev-only, must not ship to production)', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require'])->not->toHaveKey('marko/testing');
});

it('has public/index.php using Application::boot() API', function (): void {
    $indexPath = __DIR__ . '/../public/index.php';

    expect(file_exists($indexPath))->toBeTrue();

    $content = file_get_contents($indexPath);

    expect($content)
        ->toContain("require __DIR__ . '/../vendor/autoload.php'")
        ->toContain('Application::boot(dirname(__DIR__))')
        ->toContain('$app->handleRequest()');
});

it('has .env.example with placeholder values', function (): void {
    $envPath = __DIR__ . '/../.env.example';

    expect(file_exists($envPath))->toBeTrue();

    $content = file_get_contents($envPath);

    expect($content)
        ->toContain('APP_ENV=')
        ->toContain('APP_DEBUG=');
});

it('has empty app/ directory with .gitkeep', function (): void {
    $gitkeepPath = __DIR__ . '/../app/.gitkeep';

    expect(file_exists($gitkeepPath))->toBeTrue();
});

it('has empty modules/ directory with .gitkeep', function (): void {
    $gitkeepPath = __DIR__ . '/../modules/.gitkeep';

    expect(file_exists($gitkeepPath))->toBeTrue();
});

it('has empty config/ directory with .gitkeep', function (): void {
    $gitkeepPath = __DIR__ . '/../config/.gitkeep';

    expect(file_exists($gitkeepPath))->toBeTrue();
});

it('has empty storage/ directory with .gitkeep', function (): void {
    $gitkeepPath = __DIR__ . '/../storage/.gitkeep';

    expect(file_exists($gitkeepPath))->toBeTrue();
});

it('has a README.md file', function (): void {
    $packagePath = __DIR__ . '/..';

    expect(file_exists($packagePath . '/README.md'))->toBeTrue();
});

it('ships a .gitignore to generated projects', function (): void {
    $gitignorePath = __DIR__ . '/../.gitignore';

    expect(file_exists($gitignorePath))->toBeTrue();

    // The .gitignore must NOT be export-ignored, otherwise composer
    // create-project strips it from the dist archive and generated
    // projects ship without one (vendor/, .env, etc. left untracked).
    $gitattributes = file_get_contents(__DIR__ . '/../.gitattributes');

    expect($gitattributes)->not->toMatch('/^\s*\/?\.gitignore\s+export-ignore/m');
});

it('keeps composer.lock tracked in generated projects', function (): void {
    // Application projects must commit composer.lock for reproducible,
    // repeatable installs across the team. It must not be gitignored.
    $gitignore = file_get_contents(__DIR__ . '/../.gitignore');

    expect($gitignore)->not->toMatch('/^\s*composer\.lock\s*$/m');
});

it('ignores generated dependency and build directories', function (): void {
    $gitignore = file_get_contents(__DIR__ . '/../.gitignore');

    expect($gitignore)
        ->toContain('/vendor/')        // composer install output
        ->toContain('/node_modules/')  // npm/pnpm install output
        ->toContain('/.marko/')        // framework runtime state
        ->toContain('/public/build')   // vite production assets
        ->toContain('/public/hot')     // vite dev-server HMR marker
        ->toContain('/public/storage') // marko storage:link symlink
        ->toContain('.env');           // secrets
});

it('ignores storage runtime contents but keeps the directory', function (): void {
    // storage/ holds runtime output (cache, logs, debugbar) that must be
    // ignored, while .gitkeep preserves the directory on a fresh clone.
    $gitignore = file_get_contents(__DIR__ . '/../.gitignore');

    expect($gitignore)
        ->toMatch('/^\s*\/storage\/\*\s*$/m')
        ->toMatch('/^\s*!\/storage\/\.gitkeep\s*$/m')
        ->and(file_exists(__DIR__ . '/../storage/.gitkeep'))->toBeTrue();
});

it('lists marko/view-twig in the skeleton composer suggest block', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['suggest'])->toHaveKey('marko/view-twig');
});

it('lists marko/view-latte in the skeleton composer suggest block', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['suggest'])->toHaveKey('marko/view-latte');
});

it('does not add marko/view-twig to require', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require'])->not->toHaveKey('marko/view-twig');
});

it('does not add marko/view-latte to require', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require'])->not->toHaveKey('marko/view-latte');
});

it('keeps the suggest entries valid JSON', function (): void {
    $composerPath = __DIR__ . '/../composer.json';
    $content = file_get_contents($composerPath);
    $composer = json_decode($content, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($composer['suggest'])->toBeArray();
});

it('ships a root phpunit.xml in the skeleton', function (): void {
    $phpunitPath = __DIR__ . '/../phpunit.xml';

    expect(file_exists($phpunitPath))->toBeTrue();
});

it('configures testsuites that discover app and modules test directories', function (): void {
    $phpunitPath = __DIR__ . '/../phpunit.xml';
    $xml = simplexml_load_string(file_get_contents($phpunitPath));

    $directories = [];
    foreach ($xml->testsuites->testsuite as $suite) {
        foreach ($suite->directory as $dir) {
            $directories[] = (string) $dir;
        }
    }

    expect($directories)->toContain('app')
        ->and($directories)->toContain('modules')
        ->and($directories)->toContain('tests');
});

it('ships a root tests/Pest.php that references Marko\Testing\TestCase', function (): void {
    $pestPath = __DIR__ . '/../tests/Pest.php';

    expect(file_exists($pestPath))->toBeTrue();

    $content = file_get_contents($pestPath);

    expect($content)->toContain('Marko\Testing\TestCase');
});

it('ships placeholders so every directory the phpunit.xml testsuites reference exists (app, modules, tests)', function (): void {
    $base = __DIR__ . '/..';

    expect(is_dir($base . '/app'))->toBeTrue()
        ->and(is_dir($base . '/modules'))->toBeTrue()
        ->and(is_dir($base . '/tests'))->toBeTrue();
});

it('runs pest successfully on a freshly scaffolded skeleton with no added test files', function (): void {
    // Simulate a fresh skeleton install: create a temporary directory with the
    // same layout (app/.gitkeep, modules/.gitkeep, tests/Pest.php) and run
    // pest against it. Zero tests should exit 0 (green), not an error.
    $tmpDir = sys_get_temp_dir() . '/marko-skeleton-test-' . uniqid();
    mkdir($tmpDir . '/app', 0755, true);
    mkdir($tmpDir . '/modules', 0755, true);
    mkdir($tmpDir . '/tests', 0755, true);
    file_put_contents($tmpDir . '/app/.gitkeep', '');
    file_put_contents($tmpDir . '/modules/.gitkeep', '');
    file_put_contents($tmpDir . '/tests/Pest.php', file_get_contents(__DIR__ . '/Pest.php'));
    file_put_contents($tmpDir . '/phpunit.xml', file_get_contents(__DIR__ . '/../phpunit.xml'));

    $vendorPest = __DIR__ . '/../../../vendor/bin/pest';
    $vendorAutoload = __DIR__ . '/../../../vendor/autoload.php';

    $command = sprintf(
        'cd %s && /opt/homebrew/Cellar/php/8.5.1_2/bin/php -d memory_limit=512M %s -c %s --bootstrap %s --no-coverage 2>&1',
        escapeshellarg($tmpDir),
        escapeshellarg($vendorPest),
        escapeshellarg($tmpDir . '/phpunit.xml'),
        escapeshellarg($vendorAutoload),
    );

    exec($command, $output, $exitCode);

    // Clean up temporary directory recursively
    exec('rm -rf ' . escapeshellarg($tmpDir));

    expect($exitCode)->toBe(0);
});

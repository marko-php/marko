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
        ->toMatch('/^\s*!\/storage\/\.gitkeep\s*$/m');

    expect(file_exists(__DIR__ . '/../storage/.gitkeep'))->toBeTrue();
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

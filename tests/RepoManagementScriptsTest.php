<?php

declare(strict_types=1);

$binDir = dirname(__DIR__) . '/bin';

it(
    'creates bin/create-split-repos.sh that bulk-creates all 70 split repos on GitHub',
    function () use ($binDir): void {
        $path = $binDir . '/create-split-repos.sh';

        expect(file_exists($path))->toBeTrue('bin/create-split-repos.sh does not exist');

        $content = file_get_contents($path);

        expect($content)
            ->toContain('#!/usr/bin/env bash')
            ->toContain('GITHUB_ORG=')
            ->toContain('gh repo create')
            ->toContain('packages/');
    },
);

it('creates bin/register-packagist.sh that registers all 70 packages on Packagist', function () use ($binDir): void {
    $path = $binDir . '/register-packagist.sh';

    expect(file_exists($path))->toBeTrue('bin/register-packagist.sh does not exist');

    $content = file_get_contents($path);

    expect($content)
        ->toContain('#!/usr/bin/env bash')
        ->toContain('PACKAGIST_USERNAME')
        ->toContain('PACKAGIST_TOKEN')
        ->toContain('packagist.org/api/create-package')
        ->toContain('packages/');
});

it('creates bin/add-package.sh that handles the full new-package workflow', function () use ($binDir): void {
    $path = $binDir . '/add-package.sh';

    expect(file_exists($path))->toBeTrue('bin/add-package.sh does not exist');

    $content = file_get_contents($path);

    expect($content)
        ->toContain('#!/usr/bin/env bash')
        ->toContain('GITHUB_ORG=')
        ->toContain('PACKAGIST_USERNAME')
        ->toContain('PACKAGIST_TOKEN')
        ->toContain('gh repo create')
        ->toContain('packagist.org/api/create-package')
        ->toContain('composer.json');
});

it('dynamically reads package list from packages/ directory', function () use ($binDir): void {
    $createRepos = file_get_contents($binDir . '/create-split-repos.sh');
    $registerPackagist = file_get_contents($binDir . '/register-packagist.sh');

    // Scripts iterate over packages/ directory dynamically, not a hardcoded list
    expect($createRepos)
        ->toContain('packages/*/')
        ->toContain('for pkg_dir in')
        ->and($registerPackagist)
        ->toContain('packages/*/')
        ->toContain('for pkg_dir in');
});

it('skips repos that already exist without erroring', function () use ($binDir): void {
    $createRepos = file_get_contents($binDir . '/create-split-repos.sh');
    $registerPackagist = file_get_contents($binDir . '/register-packagist.sh');
    $addPackage = file_get_contents($binDir . '/add-package.sh');

    // create-split-repos.sh batch-fetches existing repos and skips ones already present
    expect($createRepos)
        ->toContain('gh repo list')
        ->toContain('Already exists')
        // register-packagist.sh handles HTTP 400 (already registered) gracefully
        ->and($registerPackagist)
        ->toContain('"400"')
        ->toContain('skipping')
        // add-package.sh checks if split repo already exists
        ->and($addPackage)
        ->toContain('gh repo view')
        ->toContain('already exists');
});

it(
    'validates required tools and credentials before running (gh, curl, jq, API tokens)',
    function () use ($binDir): void {
        $createRepos = file_get_contents($binDir . '/create-split-repos.sh');
        $registerPackagist = file_get_contents($binDir . '/register-packagist.sh');
        $addPackage = file_get_contents($binDir . '/add-package.sh');

        // create-split-repos.sh validates gh, jq, and gh auth
        expect($createRepos)
            ->toContain('command -v gh')
            ->toContain('command -v jq')
            ->toContain('gh auth status')
            // register-packagist.sh validates curl, jq, and requires API tokens
            ->and($registerPackagist)
            ->toContain('command -v curl')
            ->toContain('command -v jq')
            ->toContain('PACKAGIST_USERNAME:?')
            ->toContain('PACKAGIST_TOKEN:?')
            // add-package.sh validates gh, jq, curl, and requires API tokens
            ->and($addPackage)
            ->toContain('command -v gh')
            ->toContain('command -v jq')
            ->toContain('command -v curl')
            ->toContain('PACKAGIST_USERNAME:?')
            ->toContain('PACKAGIST_TOKEN:?');
    },
);

it('makes all scripts executable', function () use ($binDir): void {
    $scripts = [
        $binDir . '/create-split-repos.sh',
        $binDir . '/register-packagist.sh',
        $binDir . '/add-package.sh',
    ];

    foreach ($scripts as $script) {
        expect(is_executable($script))->toBeTrue("Script not executable: $script");
    }
});

/**
 * Runs bin/register-packagist.sh with a stub curl on PATH that records each repository URL
 * it is asked to register, so no request reaches Packagist.
 *
 * @param list<string> $arguments
 * @return array{exitCode: int, output: string, registered: list<string>}
 */
function runRegisterPackagist(string $binDir, array $arguments): array
{
    $stubDir = sys_get_temp_dir() . '/marko-register-packagist-' . bin2hex(random_bytes(6));
    mkdir($stubDir);
    $log = $stubDir . '/registered.log';
    file_put_contents(
        $stubDir . '/curl',
        "#!/usr/bin/env bash\n"
        . "while [[ \$# -gt 0 ]]; do\n"
        . '  if [[ "$1" == "-d" ]]; then echo "$2" >> ' . escapeshellarg($log) . "; fi\n"
        . "  shift\n"
        . "done\n"
        . "cat > /dev/null\n"
        . "printf '201'\n",
    );
    chmod($stubDir . '/curl', 0755);

    $command = 'PATH=' . escapeshellarg($stubDir . ':' . getenv('PATH'))
        . ' PACKAGIST_USERNAME=tester PACKAGIST_TOKEN=secret '
        . escapeshellarg($binDir . '/register-packagist.sh')
        . ($arguments === [] ? '' : ' ' . implode(' ', array_map('escapeshellarg', $arguments)))
        . ' 2>&1';

    exec($command, $outputLines, $exitCode);

    $registered = [];

    if (file_exists($log)) {
        foreach (file($log, FILE_IGNORE_NEW_LINES) ?: [] as $payload) {
            $registered[] = json_decode($payload, true)['repository']['url'];
        }
        unlink($log);
    }

    unlink($stubDir . '/curl');
    rmdir($stubDir);

    return ['exitCode' => $exitCode, 'output' => implode("\n", $outputLines), 'registered' => $registered];
}

it('registers only the packages named as arguments', function () use ($binDir): void {
    $result = runRegisterPackagist($binDir, ['cache', 'queue']);

    expect($result['exitCode'])->toBe(0)
        ->and($result['registered'])->toBe([
            'https://github.com/marko-php/marko-cache',
            'https://github.com/marko-php/marko-queue',
        ]);
});

it('registers every package when no arguments are given', function () use ($binDir): void {
    $result = runRegisterPackagist($binDir, []);

    $packageCount = count(glob(dirname(__DIR__) . '/packages/*/composer.json') ?: []);

    expect($result['exitCode'])->toBe(0)
        ->and($result['registered'])->toHaveCount($packageCount);
});

it('rejects an unknown package name before registering anything', function () use ($binDir): void {
    $result = runRegisterPackagist($binDir, ['cache', 'no-such-package']);

    expect($result['exitCode'])->not->toBe(0)
        ->and($result['output'])->toContain('packages/no-such-package/')
        ->and($result['registered'])->toBe([]);
});

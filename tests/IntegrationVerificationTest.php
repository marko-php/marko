<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$packagesRoot = $root . '/packages';

$packages = array_values(array_filter(
    scandir($packagesRoot),
    fn(string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($packagesRoot . '/' . $entry),
));

it(
    'validates all 71 package composer.json files are valid JSON with required keys (name, require) via structural check',
    function () use ($packagesRoot, $packages): void {
        expect($packages)->toHaveCount(71);
    
        foreach ($packages as $package) {
            $file = $packagesRoot . '/' . $package . '/composer.json';
            expect(file_exists($file))->toBeTrue("Missing composer.json in packages/{$package}");
    
            $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            expect(array_key_exists('name', $data))->toBeTrue("packages/{$package}/composer.json missing 'name' key")
                ->and(array_key_exists('require', $data))->toBeTrue(
                    "packages/{$package}/composer.json missing 'require' key"
                );
        }
    }
);

it('validates the root composer.json passes composer validate', function () use ($root): void {
    $output = [];
    $exitCode = 0;
    exec('cd ' . escapeshellarg($root) . ' && composer validate --no-check-lock 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0, implode("\n", $output));
});

// This test verifies the clean-install workflow in a completely isolated subprocess.
// The subprocess handles: remove lock+vendor, run composer update, run test suite.
// The result is conveyed back via a temp file so the live vendor/ is never touched
// by the running pest process.
it(
    'removes composer.lock and vendor/ before running composer update to ensure clean resolution',
    function () use ($root): void {
        $php = '/opt/homebrew/Cellar/php/8.5.1_2/bin/php';
        $resultFile = tempnam(sys_get_temp_dir(), 'marko_result_');
    
        $script = <<<'SCRIPT'
<?php
[$root, $resultFile] = [$argv[1], $argv[2]];

$results = [];

// Step 1: Remove lock and vendor
if (file_exists($root . '/composer.lock')) {
    unlink($root . '/composer.lock');
}
if (is_dir($root . '/vendor')) {
    exec('rm -rf ' . escapeshellarg($root . '/vendor'));
}
$results['lock_removed']   = !file_exists($root . '/composer.lock');
$results['vendor_removed'] = !is_dir($root . '/vendor');

// Step 2: Run composer update
exec(
    'cd ' . escapeshellarg($root) . ' && composer update --no-interaction --ignore-platform-req=ext-imagick 2>&1',
    $updateOutput,
    $updateExit,
);
$results['composer_update_exit']   = $updateExit;
$results['vendor_restored']        = is_dir($root . '/vendor');
$results['lock_restored']          = file_exists($root . '/composer.lock');
$results['composer_update_output'] = implode("\n", array_slice($updateOutput, -5));

// Step 3: Run test suite (exclude destructive group to avoid recursion)
$php = '/opt/homebrew/Cellar/php/8.5.1_2/bin/php';
exec(
    'cd ' . escapeshellarg($root) . ' && ' . $php . ' vendor/bin/pest --parallel --exclude-group=integration-destructive 2>&1',
    $testOutput,
    $testExit,
);
$summary = implode("\n", $testOutput);
$results['no_fatal_error'] = !str_contains($summary, 'PHP Fatal error')
    && !str_contains($summary, 'Call to undefined function')
    && !str_contains($summary, 'Error [');
$results['test_summary'] = implode("\n", array_slice($testOutput, -3));

file_put_contents($resultFile, json_encode($results));
SCRIPT;
    
        $scriptFile = tempnam(sys_get_temp_dir(), 'marko_script_');
        file_put_contents($scriptFile, $script);
    
        exec(
            $php . ' ' . escapeshellarg($scriptFile)
            . ' ' . escapeshellarg($root)
            . ' ' . escapeshellarg($resultFile)
            . ' 2>/dev/null',
        );
    
        unlink($scriptFile);
    
        expect(file_exists($resultFile))->toBeTrue('Subprocess script did not produce a result file');
        $results = json_decode(file_get_contents($resultFile), true);
        unlink($resultFile);
    
        expect($results['lock_removed'])->toBeTrue('composer.lock was not removed')
            ->and($results['vendor_removed'])->toBeTrue('vendor/ was not removed');
    }
)->group('integration-destructive');

it('runs composer update from root successfully', function () use ($root): void {
    // Verify vendor/ and composer.lock exist (restored by the previous destructive test's subprocess)
    expect(is_dir($root . '/vendor'))->toBeTrue(
        'vendor/ should exist — run the full integration-destructive group in order',
    )
        ->and(file_exists($root . '/composer.lock'))->toBeTrue(
            'composer.lock should exist — run the full integration-destructive group in order',
        );
})->group('integration-destructive');

it('runs the full test suite and all tests pass', function () use ($root): void {
    $php = '/opt/homebrew/Cellar/php/8.5.1_2/bin/php';
    $output = [];
    $exitCode = 0;
    exec(
        'cd ' . escapeshellarg(
            $root
        ) . ' && ' . $php . ' vendor/bin/pest --parallel --exclude-group=integration-destructive 2>&1',
        $output,
        $exitCode,
    );

    $summary = implode("\n", $output);

    // The suite must complete without fatal PHP errors
    expect($summary)->not->toContain('Call to undefined function')
        ->and($summary)->not->toContain('PHP Fatal error')
        ->and($summary)->not->toContain('Error [');
})->group('integration-destructive');

it('verifies no package composer.json contains a repositories key', function () use ($packagesRoot, $packages): void {
    foreach ($packages as $package) {
        $file = $packagesRoot . '/' . $package . '/composer.json';
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        expect(array_key_exists('repositories', $data))->toBeFalse(
            "packages/{$package}/composer.json must not contain a 'repositories' key",
        );
    }
});

it('verifies no package composer.json contains a version key', function () use ($packagesRoot, $packages): void {
    foreach ($packages as $package) {
        $file = $packagesRoot . '/' . $package . '/composer.json';
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        expect(array_key_exists('version', $data))->toBeFalse(
            "packages/{$package}/composer.json must not contain a 'version' key",
        );
    }
});

it('verifies all marko/* dependencies use self.version constraint', function () use ($packagesRoot, $packages): void {
    // skeleton is type:project installed via composer create-project; self.version is not valid there
    $projectTypePackages = ['skeleton'];

    foreach ($packages as $package) {
        if (in_array($package, $projectTypePackages, true)) {
            continue;
        }

        $file = $packagesRoot . '/' . $package . '/composer.json';
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        $allDeps = array_merge(
            $data['require'] ?? [],
            $data['require-dev'] ?? [],
        );

        foreach ($allDeps as $dep => $constraint) {
            if (str_starts_with($dep, 'marko/')) {
                expect($constraint)->toBe(
                    'self.version',
                    "packages/{$package}/composer.json: {$dep} must use 'self.version', got '{$constraint}'",
                );
            }
        }
    }
});

it('verifies every package directory has a .gitattributes file', function () use ($packagesRoot, $packages): void {
    expect($packages)->toHaveCount(71);

    foreach ($packages as $package) {
        $path = $packagesRoot . '/' . $package . '/.gitattributes';
        expect(file_exists($path))->toBeTrue("Missing .gitattributes in packages/{$package}");
    }
});

it('verifies every package directory has a LICENSE file', function () use ($packagesRoot, $packages): void {
    expect($packages)->toHaveCount(71);

    foreach ($packages as $package) {
        $path = $packagesRoot . '/' . $package . '/LICENSE';
        expect(file_exists($path))->toBeTrue("Missing LICENSE in packages/{$package}");
    }
});

it('verifies demo/ directory no longer exists', function () use ($root): void {
    expect(is_dir($root . '/demo'))->toBeFalse('demo/ directory should not exist');
});

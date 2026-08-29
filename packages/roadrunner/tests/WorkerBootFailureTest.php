<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests;

describe('worker.php', function (): void {
    it('exits with a loud error when the application fails to boot', function (): void {
        $fixtureRoot = sys_get_temp_dir() . '/marko-worker-boot-failure-' . bin2hex(random_bytes(8));
        $failureMessage = 'simulated boot failure for worker boot test ' . bin2hex(random_bytes(4));

        mkdir($fixtureRoot . '/vendor', 0755, true);
        mkdir($fixtureRoot . '/app/failing', 0755, true);
        mkdir($fixtureRoot . '/modules', 0755, true);

        // Delegates to this monorepo's real autoloader — everything about
        // this fixture except the failing module's boot callback is real.
        $realAutoload = monorepoRootPath() . '/vendor/autoload.php';
        file_put_contents(
            $fixtureRoot . '/vendor/autoload.php',
            "<?php\nrequire " . var_export($realAutoload, true) . ';' . "\n",
        );

        file_put_contents(
            $fixtureRoot . '/app/failing/composer.json',
            json_encode([
                'name' => 'fixture/failing-boot',
                'extra' => ['marko' => ['module' => true]],
            ], JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            $fixtureRoot . '/app/failing/module.php',
            "<?php\nreturn ['boot' => function () { throw new \\RuntimeException("
                . var_export($failureMessage, true) . '); }];' . "\n",
        );

        $workerScript = dirname(__DIR__) . '/worker.php';

        $process = proc_open(
            [PHP_BINARY, $workerScript],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            array_merge($_ENV, ['MARKO_BASE_PATH' => $fixtureRoot]),
        );

        try {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            expect($exitCode)->not->toBe(0)
                ->and($stdout)->toBe('')
                ->and($stderr)->toContain($failureMessage);
        } finally {
            unlink($fixtureRoot . '/app/failing/module.php');
            unlink($fixtureRoot . '/app/failing/composer.json');
            rmdir($fixtureRoot . '/app/failing');
            rmdir($fixtureRoot . '/app');
            unlink($fixtureRoot . '/vendor/autoload.php');
            rmdir($fixtureRoot . '/vendor');
            rmdir($fixtureRoot . '/modules');
            rmdir($fixtureRoot);
        }
    });
});

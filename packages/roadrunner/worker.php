<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Roadrunner\GuardRails\UnsafePackageChecker;
use Marko\Roadrunner\Http\Psr7RequestBridge;
use Marko\Roadrunner\Http\Psr7ResponseBridge;
use Marko\Roadrunner\Worker\BasePathResolver;
use Marko\Roadrunner\Worker\SplComposerAutoloaderLocator;
use Marko\Roadrunner\Worker\WorkerLogger;
use Marko\Roadrunner\Worker\WorkerRequestHandler;
use Marko\Roadrunner\Worker\WorkerSafeExceptionHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;

// This script ships INSIDE the package at packages/roadrunner/worker.php and
// is executed by RoadRunner as vendor/marko/roadrunner/worker.php. Under a
// Composer path repository (exactly how this monorepo — and any downstream
// app developing against it locally — installs its own packages),
// vendor/marko/roadrunner is a symlink back into the package source tree.
//
// PHP resolves __FILE__/__DIR__ through symlink targets, so this script
// cannot locate the project it belongs to via __DIR__: doing so would land
// in the package source tree instead. See Marko\Roadrunner\Worker\BasePathResolver
// for the full explanation and the validated, symlink-independent resolution
// this file delegates to once the framework's own autoloader is available.
//
// Before that autoloader exists, no Marko class can be referenced at all —
// so this first step is unavoidably inline: find *some* vendor/autoload.php
// to require, preferring the MARKO_BASE_PATH environment variable that the
// shipped .rr.yaml always sets, falling back to the working directory.
$bootstrapBasePath = rtrim((string) (getenv('MARKO_BASE_PATH') ?: getcwd()), '/');

require $bootstrapBasePath . '/vendor/autoload.php';

// Now that Marko classes are autoloadable, get the authoritative, validated
// base path — anchored on the just-registered Composer ClassLoader's own
// (never-symlinked) file, correcting the bootstrap guess above if needed.
$basePath = (new BasePathResolver(new SplComposerAutoloaderLocator()))->resolve();

try {
    $app = Application::boot($basePath);

    $warnings = $app->container->get(UnsafePackageChecker::class)->check();
    foreach ($warnings as $warning) {
        fwrite(STDERR, "[worker] $warning" . PHP_EOL);
    }
} catch (Throwable $throwable) {
    // Boot failure must not loop: write the real reason to STDERR (the only
    // stream a worker may write to — STDOUT carries the goridge protocol)
    // and exit non-zero so RoadRunner restarts the worker instead of this
    // process answering every request with a 500 forever.
    fwrite(STDERR, sprintf(
        'Marko application failed to boot: %s: %s in %s:%d%s%s' . PHP_EOL,
        $throwable::class,
        $throwable->getMessage(),
        $throwable->getFile(),
        $throwable->getLine(),
        PHP_EOL,
        $throwable->getTraceAsString(),
    ));

    exit(1);
}

$development = strtolower((string) (getenv('APP_ENV') ?: '')) === 'development';

$logger = $app->container->has(PsrLoggerInterface::class)
    ? $app->container->get(PsrLoggerInterface::class)
    : null;
$workerLogger = new WorkerLogger($logger);

// Installed after boot: module boot callbacks (e.g. marko/errors-simple's)
// are what install a handler that would otherwise echo to STDOUT, so this
// must run last to be the one PHP actually keeps.
(new WorkerSafeExceptionHandler($workerLogger))->install();

$psr17Factory = new Psr17Factory();
$psr7Worker = new PSR7Worker(
    RoadRunnerWorker::create(),
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
);

$requestHandler = new WorkerRequestHandler(
    psr7Worker: $psr7Worker,
    router: $app->router,
    requestBridge: new Psr7RequestBridge(),
    responseBridge: new Psr7ResponseBridge(),
    logger: $workerLogger,
    development: $development,
);

$requestHandler->run();

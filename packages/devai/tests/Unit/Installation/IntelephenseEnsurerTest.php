<?php

declare(strict_types=1);

use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Installation\IntelephenseEnsurer;
use Marko\DevAi\Process\CommandRunnerInterface;

// ---------------------------------------------------------------------------
// Fake runner
// ---------------------------------------------------------------------------

function makeIntelephenseRunner(
    bool $intelephenseOnPath = false,
    bool $npmOnPath = true,
    int $npmInstallExitCode = 0,
    string $npmInstallStderr = '',
): CommandRunnerInterface {
    return new class ($intelephenseOnPath, $npmOnPath, $npmInstallExitCode, $npmInstallStderr) implements CommandRunnerInterface
    {
        /** @var list<array{string, list<string>}> */
        public array $calls = [];

        public function __construct(
            private bool $intelephenseOnPath,
            private bool $npmOnPath,
            private int $npmInstallExitCode,
            private string $npmInstallStderr,
        ) {}

        public function run(
            string $command,
            array $args = [],
        ): array {
            $this->calls[] = [$command, $args];

            if ($command === 'npm') {
                return [
                    'exitCode' => $this->npmInstallExitCode,
                    'stdout' => '',
                    'stderr' => $this->npmInstallStderr,
                ];
            }

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(
            string $binary,
        ): bool {
            return match ($binary) {
                'intelephense' => $this->intelephenseOnPath,
                'npm' => $this->npmOnPath,
                default => false,
            };
        }
    };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('returns alreadyInstalled when intelephense is on PATH', function (): void {
    $runner = makeIntelephenseRunner(intelephenseOnPath: true);
    $ensurer = new IntelephenseEnsurer($runner);

    $result = $ensurer->ensure(skip: false);

    expect($result->isAlreadyInstalled())->toBeTrue()
        ->and($result->isInstalled())->toBeFalse()
        ->and($result->isSkipped())->toBeFalse();
});

it('npm installs the pinned intelephense globally when it is missing', function (): void {
    $runner = makeIntelephenseRunner(intelephenseOnPath: false, npmOnPath: true);
    $ensurer = new IntelephenseEnsurer($runner);

    $ensurer->ensure(skip: false);

    $npmCalls = array_filter($runner->calls, fn ($call) => $call[0] === 'npm');
    expect(array_values($npmCalls))->not->toBeEmpty()
        ->and($npmCalls[array_key_first($npmCalls)][1])->toBe(['install', '-g', IntelephenseEnsurer::PACKAGE])
        ->and(IntelephenseEnsurer::PACKAGE)->toBe('intelephense@' . IntelephenseEnsurer::VERSION);
});

it('returns installed result on successful npm install', function (): void {
    $runner = makeIntelephenseRunner(intelephenseOnPath: false, npmOnPath: true, npmInstallExitCode: 0);
    $ensurer = new IntelephenseEnsurer($runner);

    $result = $ensurer->ensure(skip: false);

    expect($result->isInstalled())->toBeTrue()
        ->and($result->isAlreadyInstalled())->toBeFalse()
        ->and($result->isSkipped())->toBeFalse();
});

it('throws DevAiInstallException when npm is missing', function (): void {
    $runner = makeIntelephenseRunner(intelephenseOnPath: false, npmOnPath: false);
    $ensurer = new IntelephenseEnsurer($runner);

    expect(fn () => $ensurer->ensure(skip: false))
        ->toThrow(DevAiInstallException::class);
});

it('throws DevAiInstallException when npm install exits non-zero', function (): void {
    $runner = makeIntelephenseRunner(
        intelephenseOnPath: false,
        npmOnPath: true,
        npmInstallExitCode: 1,
        npmInstallStderr: 'npm ERR! permission denied',
    );
    $ensurer = new IntelephenseEnsurer($runner);

    expect(fn () => $ensurer->ensure(skip: false))
        ->toThrow(DevAiInstallException::class);
});

it('returns skipped without checking PATH when skip flag is true', function (): void {
    // Pass impossible values to confirm PATH is never checked
    $runner = makeIntelephenseRunner(intelephenseOnPath: false, npmOnPath: false);
    $ensurer = new IntelephenseEnsurer($runner);

    $result = $ensurer->ensure(skip: true);

    expect($result->isSkipped())->toBeTrue()
        ->and($result->isInstalled())->toBeFalse()
        ->and($result->isAlreadyInstalled())->toBeFalse();
});

it('does not invoke npm install when skip flag is true', function (): void {
    $runner = makeIntelephenseRunner(intelephenseOnPath: false, npmOnPath: true);
    $ensurer = new IntelephenseEnsurer($runner);

    $ensurer->ensure(skip: true);

    $npmCalls = array_filter($runner->calls, fn ($call) => $call[0] === 'npm');
    expect(array_values($npmCalls))->toBeEmpty();
});

it('pins intelephense to an exact semver release, never a range or tag', function (): void {
    expect(IntelephenseEnsurer::VERSION)->toMatch('/^\d+\.\d+\.\d+$/')
        ->and(IntelephenseEnsurer::PACKAGE)->toBe('intelephense@' . IntelephenseEnsurer::VERSION);
});

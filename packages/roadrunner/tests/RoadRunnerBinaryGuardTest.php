<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests;

use RuntimeException;

it('fails instead of skipping when the roadrunner binary is missing in required mode', function (): void {
    $exception = catchThrowable(
        fn (): bool => roadRunnerUnavailableFor(null, ['MARKO_INTEGRATION_REQUIRED' => '1']),
    );

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())
        ->toContain('MARKO_INTEGRATION_REQUIRED is set but')
        ->toContain('RoadRunner binary not found');
});

it(
    'skips with the binary-not-found reason when the roadrunner binary is missing outside required mode',
    function (): void {
        expect(roadRunnerUnavailableFor(null, []))->toBeTrue()
            ->and(roadRunnerSkipReason())
            ->toContain('RoadRunner binary not found')
            ->toContain('rr get-binary');
    },
);

it('does not skip or fail when the roadrunner binary is present, even in required mode', function (): void {
    expect(roadRunnerUnavailableFor('/path/to/rr', ['MARKO_INTEGRATION_REQUIRED' => '1']))->toBeFalse();
});

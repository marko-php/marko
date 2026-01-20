<?php

declare(strict_types=1);

use Marko\Errors\Severity;

it('has an error case for fatal errors', function (): void {
    expect(Severity::Error)->toBeInstanceOf(Severity::class);
    expect(Severity::Error->value)->toBe('error');
});

it('has a warning case for warnings', function (): void {
    expect(Severity::Warning)->toBeInstanceOf(Severity::class);
    expect(Severity::Warning->value)->toBe('warning');
});

it('has a notice case for notices', function (): void {
    expect(Severity::Notice)->toBeInstanceOf(Severity::class);
    expect(Severity::Notice->value)->toBe('notice');
});

it('has a deprecated case for deprecation warnings', function (): void {
    expect(Severity::Deprecated)->toBeInstanceOf(Severity::class);
    expect(Severity::Deprecated->value)->toBe('deprecated');
});

it('creates severity from PHP error level constant', function (): void {
    $severity = Severity::fromErrorLevel(E_ERROR);

    expect($severity)->toBeInstanceOf(Severity::class);
});

it('returns error for E_ERROR and E_USER_ERROR', function (): void {
    expect(Severity::fromErrorLevel(E_ERROR))->toBe(Severity::Error);
    expect(Severity::fromErrorLevel(E_USER_ERROR))->toBe(Severity::Error);
});

it('returns warning for E_WARNING and E_USER_WARNING', function (): void {
    expect(Severity::fromErrorLevel(E_WARNING))->toBe(Severity::Warning);
    expect(Severity::fromErrorLevel(E_USER_WARNING))->toBe(Severity::Warning);
});

it('returns notice for E_NOTICE and E_USER_NOTICE', function (): void {
    expect(Severity::fromErrorLevel(E_NOTICE))->toBe(Severity::Notice);
    expect(Severity::fromErrorLevel(E_USER_NOTICE))->toBe(Severity::Notice);
});

it('returns deprecated for E_DEPRECATED and E_USER_DEPRECATED', function (): void {
    expect(Severity::fromErrorLevel(E_DEPRECATED))->toBe(Severity::Deprecated);
    expect(Severity::fromErrorLevel(E_USER_DEPRECATED))->toBe(Severity::Deprecated);
});

it('provides human readable label for each severity', function (): void {
    expect(Severity::Error->label())->toBe('Error');
    expect(Severity::Warning->label())->toBe('Warning');
    expect(Severity::Notice->label())->toBe('Notice');
    expect(Severity::Deprecated->label())->toBe('Deprecated');
});

it('provides ANSI color code for each severity', function (): void {
    expect(Severity::Error->color())->toBe("\033[31m");
    expect(Severity::Warning->color())->toBe("\033[33m");
    expect(Severity::Notice->color())->toBe("\033[36m");
    expect(Severity::Deprecated->color())->toBe("\033[35m");
});

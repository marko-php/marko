<?php

declare(strict_types=1);

namespace Marko\Errors\Tests\Unit;

use DateTimeImmutable;
use Exception;
use Marko\Core\Exceptions\MarkoException;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use ReflectionClass;

describe('ErrorReport', function (): void {
    it('creates report from throwable with message and code', function (): void {
        $exception = new Exception('Test error message', 42);

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report->message)->toBe('Test error message');
        expect($report->code)->toBe(42);
    });

    it('captures the throwable instance', function (): void {
        $exception = new Exception('Test error');

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report->throwable)->toBe($exception);
    });

    it('captures the stack trace as array', function (): void {
        $exception = new Exception('Test error');

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report->trace)->toBeArray();
        expect($report->trace)->toBe($exception->getTrace());
    });

    it('captures the file and line where error occurred', function (): void {
        $exception = new Exception('Test error');

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report->file)->toBe($exception->getFile());
        expect($report->line)->toBe($exception->getLine());
    });

    it('captures the severity level', function (): void {
        $exception = new Exception('Test error');

        $report = ErrorReport::fromThrowable($exception, Severity::Warning);

        expect($report->severity)->toBe(Severity::Warning);
    });

    it('captures the timestamp of when error occurred', function (): void {
        $before = new DateTimeImmutable();
        $exception = new Exception('Test error');

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $after = new DateTimeImmutable();

        expect($report->timestamp)->toBeInstanceOf(DateTimeImmutable::class);
        expect($report->timestamp >= $before)->toBeTrue();
        expect($report->timestamp <= $after)->toBeTrue();
    });

    it('extracts context from MarkoException', function (): void {
        $exception = new MarkoException(
            message: 'Test error',
            context: 'This happened during module loading',
        );

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report->context)->toBe('This happened during module loading');
    });

    it('extracts suggestion from MarkoException', function (): void {
        $exception = new MarkoException(
            message: 'Test error',
            suggestion: 'Try restarting the server',
        );

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report->suggestion)->toBe('Try restarting the server');
    });

    it('returns empty context for non-MarkoException', function (): void {
        $exception = new Exception('Test error');

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report->context)->toBe('');
    });

    it('returns empty suggestion for non-MarkoException', function (): void {
        $exception = new Exception('Test error');

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report->suggestion)->toBe('');
    });

    it('captures previous exception when present', function (): void {
        $previous = new Exception('Previous error');
        $exception = new Exception('Test error', 0, $previous);

        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report->previous)->toBe($previous);
    });

    it('provides unique identifier for the error report', function (): void {
        $exception = new Exception('Test error');

        $report1 = ErrorReport::fromThrowable($exception, Severity::Error);
        $report2 = ErrorReport::fromThrowable($exception, Severity::Error);

        expect($report1->id)->toBeString();
        expect($report1->id)->not->toBeEmpty();
        expect($report1->id)->not->toBe($report2->id);
    });

    it('is immutable after creation', function (): void {
        $reflection = new ReflectionClass(ErrorReport::class);

        expect($reflection->isReadOnly())->toBeTrue();
    });
});

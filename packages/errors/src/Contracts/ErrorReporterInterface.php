<?php

declare(strict_types=1);

namespace Marko\Errors\Contracts;

use Marko\Errors\ErrorReport;

/**
 * Contract for reporting errors to external services.
 *
 * Implementations send error reports to external monitoring services
 * such as Sentry, Bugsnag, Rollbar, or custom logging endpoints.
 * This enables centralized error tracking and alerting across
 * distributed systems.
 */
interface ErrorReporterInterface
{
    /**
     * Report an error to the external service.
     */
    public function report(ErrorReport $report): void;

    /**
     * Determine if the error should be reported.
     *
     * Use this to filter errors based on severity, environment,
     * rate limiting, or other criteria before sending to the
     * external service.
     */
    public function shouldReport(ErrorReport $report): bool;
}

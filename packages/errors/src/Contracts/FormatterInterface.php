<?php

declare(strict_types=1);

namespace Marko\Errors\Contracts;

use Marko\Errors\ErrorReport;

/**
 * Contract for formatting error reports.
 *
 * Implementations convert ErrorReport objects into human-readable
 * output formats (text, HTML, JSON, etc.) for display or logging.
 */
interface FormatterInterface
{
    /**
     * Format an error report for output.
     *
     * @param ErrorReport $report The error report to format
     * @return string The formatted output
     */
    public function format(ErrorReport $report): string;
}

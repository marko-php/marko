<?php

declare(strict_types=1);

namespace Marko\Mcp\Plugins;

use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Path\ProjectPaths;
use Marko\Errors\ErrorReport;
use Marko\Mcp\Tools\Runtime\Adapters\FileErrorTracker;

/**
 * Auto-captures the most recent error to {projectRoot}/storage/last_error.json
 * so the MCP `last_error` tool can return it. The plugin targets the
 * ErrorHandlerInterface so it works regardless of which driver
 * (errors-simple, errors-advanced, etc.) is installed.
 */
#[Plugin(target: 'Marko\\Errors\\Contracts\\ErrorHandlerInterface')]
readonly class PersistLastErrorPlugin
{
    public function __construct(
        private ProjectPaths $paths,
    ) {}

    #[Before(method: 'handle')]
    public function recordOnHandle(ErrorReport $report): void
    {
        FileErrorTracker::record(
            $this->paths->base . '/storage/last_error.json',
            $report->throwable,
        );
    }
}

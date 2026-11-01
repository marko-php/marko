<?php

declare(strict_types=1);

namespace Marko\DevAi\Contract;

/**
 * An agent that can finish install() but still has something the user must act on
 * (e.g. a CLI it needs was missing). The orchestrator drains these notices into the
 * install summary after the agent runs, so they are printed instead of lost.
 */
interface ReportsInstallNoticesInterface
{
    /**
     * Return the notices recorded since the last call, and clear them.
     *
     * @return list<string>
     */
    public function takeNotices(): array;
}

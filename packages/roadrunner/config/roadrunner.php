<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Acknowledged Unsafe Packages
    |--------------------------------------------------------------------------
    |
    | Packages listed here are known to be unsafe under a long-running worker
    | (see Marko\Roadrunner\GuardRails\UnsafePackageChecker) but are being
    | explicitly acknowledged as installed for a reason unrelated to this
    | worker — e.g. served by a separate FPM pool, a transitive dependency,
    | or a retired endpoint. Acknowledging a package downgrades the boot-time
    | refusal to a warning; it does not remove any per-request protection
    | that package's bridge may still enforce.
    |
    */
    'acknowledged_unsafe_packages' => [],
];

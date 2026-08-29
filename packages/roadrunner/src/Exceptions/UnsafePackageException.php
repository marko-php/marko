<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class UnsafePackageException extends MarkoException
{
    public static function incompatibleWithWorkerMode(
        string $package,
        string $reason,
        string $configKey,
    ): self {
        return new self(
            message: "Package '$package' cannot run correctly under RoadRunner's long-running worker model: $reason",
            context: "While checking installed modules against worker-mode compatibility, before starting the worker's accept loop",
            suggestion: "If '$package' is not actually reachable through this worker (e.g. served by a separate FPM pool, a transitive dependency, or a retired endpoint), acknowledge it explicitly by adding it to the config array at '$configKey', e.g. '$configKey' => ['$package']. "
                . "This downgrades the boot-time refusal to a warning — it is only a courtesy check. The real protection stays in place: any request that still reaches this package's bridge under this worker throws per request, so you get a loud 500 on that route, not a silently truncated response.",
        );
    }
}

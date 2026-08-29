<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Config;

/**
 * Renders the default `.rr.yaml` shipped with the `rr:serve` command.
 *
 * The generated config is a working starting point, not just a stub: it points
 * at the packaged worker, serves static files from `public/`, and carries
 * conservative pool limits so a leaking worker cannot take a production box down.
 */
class RrYamlTemplate
{
    /**
     * Recycle each worker after this many requests. A safety net for any memory
     * leak the task 005 profiling spike did not catch — cheap production insurance.
     */
    private const int MAX_JOBS = 64;

    /**
     * Kill and replace a worker once it grows past this memory ceiling (MB).
     * Same rationale as MAX_JOBS: bound the blast radius of an undetected leak.
     */
    private const int MAX_WORKER_MEMORY_MB = 128;

    public static function render(string $basePath): string
    {
        $maxJobs = self::MAX_JOBS;
        $maxWorkerMemory = self::MAX_WORKER_MEMORY_MB;

        return <<<YAML
        version: "3"

        server:
          command: "php vendor/marko/roadrunner/worker.php"
          relay: pipes
          env:
            MARKO_BASE_PATH: "$basePath"

        http:
          address: 0.0.0.0:8080
          static:
            dir: public
            forbid:
              - .php
              - .htaccess
          pool:
            # num_workers intentionally left unset — RoadRunner defaults to CPU count.
            # max_jobs recycles each worker after N requests: a safety net for any
            # memory leak the profiling spike (task 005) did not catch, and the
            # cheapest production insurance in the plan.
            max_jobs: $maxJobs
            supervisor:
              # Kill and replace a worker once it grows past this memory ceiling (MB).
              max_worker_memory: $maxWorkerMemory

        YAML;
    }
}

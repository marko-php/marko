<?php

declare(strict_types=1);

namespace Marko\Health\Controller;

use Marko\Health\Registry\HealthCheckRegistry;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

readonly class HealthController
{
    public function __construct(
        private HealthCheckRegistry $registry,
    ) {}

    #[Get('/health')]
    public function index(): Response
    {
        $results = $this->registry->run();
        $overallStatus = $this->aggregateStatus($results);
        $statusCode = $overallStatus === HealthStatus::Unhealthy ? 503 : 200;

        return Response::json(
            data: [
                'status' => $overallStatus->value,
                'checks' => array_map(
                    fn (HealthResult $result) => [
                        'name' => $result->name,
                        'status' => $result->status->value,
                        'message' => $result->message,
                        'duration' => $result->duration,
                    ],
                    $results,
                ),
            ],
            statusCode: $statusCode,
        );
    }

    /**
     * @param array<HealthResult> $results
     */
    private function aggregateStatus(array $results): HealthStatus
    {
        if (array_any($results, fn (HealthResult $result) => $result->isUnhealthy())) {
            return HealthStatus::Unhealthy;
        }

        if (array_any($results, fn (HealthResult $result) => $result->isDegraded())) {
            return HealthStatus::Degraded;
        }

        return HealthStatus::Healthy;
    }
}

<?php

declare(strict_types=1);

namespace Marko\Health\Controller;

use JsonException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Health\Config\HealthConfig;
use Marko\Health\Exceptions\HealthException;
use Marko\Health\Registry\HealthCheckRegistry;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

readonly class HealthController
{
    public const string SECRET_HEADER = 'X-Health-Secret';

    public const string SECRET_QUERY_PARAMETER = 'secret';

    public function __construct(
        private HealthCheckRegistry $registry,
        private HealthConfig $healthConfig,
    ) {}

    /**
     * @throws JsonException|HttpException|ConfigNotFoundException|HealthException
     */
    #[Get('/health')]
    public function index(
        Request $request,
    ): Response {
        // Reject before running any check, so an unauthenticated caller
        // cannot trigger the checks' cache and filesystem writes.
        if (!$this->isAuthorized($request)) {
            throw HttpException::notFound();
        }

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
     * @throws ConfigNotFoundException|HealthException
     */
    private function isAuthorized(
        Request $request,
    ): bool {
        $secret = $this->healthConfig->getSecret();

        if ($secret === null) {
            return true;
        }

        $provided = $request->header(self::SECRET_HEADER)
            ?? $request->query(self::SECRET_QUERY_PARAMETER);

        return is_string($provided)
            && hash_equals($secret, $provided);
    }

    /**
     * @param array<HealthResult> $results
     */
    private function aggregateStatus(
        array $results,
    ): HealthStatus {
        if (array_any($results, fn (HealthResult $result) => $result->isUnhealthy())) {
            return HealthStatus::Unhealthy;
        }

        if (array_any($results, fn (HealthResult $result) => $result->isDegraded())) {
            return HealthStatus::Degraded;
        }

        return HealthStatus::Healthy;
    }
}

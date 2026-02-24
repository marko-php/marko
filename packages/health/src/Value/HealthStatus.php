<?php

declare(strict_types=1);

namespace Marko\Health\Value;

enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';
}

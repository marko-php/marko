<?php

declare(strict_types=1);

namespace Marko\AdminApi\Config;

interface AdminApiConfigInterface
{
    public function getVersion(): string;

    public function getRateLimit(): int;

    public function getGuardName(): string;
}

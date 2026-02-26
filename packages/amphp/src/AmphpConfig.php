<?php

declare(strict_types=1);

namespace Marko\Amphp;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class AmphpConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function shutdownTimeout(): int
    {
        return $this->config->getInt('amphp.shutdown_timeout');
    }
}

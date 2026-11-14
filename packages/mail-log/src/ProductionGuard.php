<?php

declare(strict_types=1);

namespace Marko\Mail\Log;

use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Environment\AppEnvironment;
use Marko\Mail\Log\Config\MailLogConfig;
use Marko\Mail\Log\Exceptions\MailLogException;

/**
 * Boot-time check that refuses to run the log mail driver in production unless
 * mail-log.allow_production explicitly opts in.
 */
readonly class ProductionGuard
{
    public function __construct(
        private AppEnvironment $appEnvironment,
        private MailLogConfig $config,
    ) {}

    /**
     * @throws MailLogException|ConfigException|ConfigNotFoundException
     */
    public function check(): void
    {
        if (
            $this->appEnvironment->isProduction()
            && !$this->config->allowProduction()
        ) {
            throw MailLogException::productionNotAllowed($this->appEnvironment->name());
        }
    }
}

<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Core\Container\ContainerInterface;

interface ContainerAwareJobInterface
{
    public function setContainer(ContainerInterface $container): void;

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void;
}

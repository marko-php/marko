<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Marko\Config\ConfigRepositoryInterface;

readonly class LatteViewConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function strictTypes(): bool
    {
        return $this->config->getBool('view.strict_types');
    }
}

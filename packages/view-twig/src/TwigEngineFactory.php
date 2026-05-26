<?php

declare(strict_types=1);

namespace Marko\View\Twig;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

readonly class TwigEngineFactory
{
    public function __construct(
        private TwigViewConfig $config,
    ) {}

    public function create(): Environment
    {
        $loader = new ArrayLoader();

        return new Environment($loader, [
            'cache' => $this->config->cacheDirectory(),
            'auto_reload' => $this->config->autoRefresh(),
            'strict_variables' => $this->config->strictVariables(),
            'autoescape' => $this->config->autoescape(),
            'debug' => $this->config->debug(),
            'charset' => $this->config->charset(),
        ]);
    }
}

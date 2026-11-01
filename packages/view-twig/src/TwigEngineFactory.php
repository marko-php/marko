<?php

declare(strict_types=1);

namespace Marko\View\Twig;

use Marko\View\CacheDirectoryGuard;
use Marko\View\Exceptions\InsecureCacheDirectoryException;
use Marko\View\TemplateResolverInterface;
use Twig\Environment;

/**
 * Builds fully configured Twig environments: cache directory, options,
 * extensions, and a ModuleLoader so templates and their includes resolve
 * through the module template resolver.
 */
readonly class TwigEngineFactory
{
    public function __construct(
        private TwigViewConfig $config,
        private TemplateFunctionExtension $templateFunctionExtension,
        private CacheDirectoryGuard $cacheDirectoryGuard,
        private TemplateResolverInterface $resolver,
    ) {}

    /**
     * Build an environment that reloads compiled templates per view.auto_refresh.
     *
     * @throws InsecureCacheDirectoryException
     */
    public function create(): Environment
    {
        return $this->build($this->config->autoRefresh());
    }

    /**
     * Build an environment that always recompiles a template changed since
     * its last compile, whatever view.auto_refresh says.
     *
     * @throws InsecureCacheDirectoryException
     */
    public function createWithAutoRefresh(): Environment
    {
        return $this->build(true);
    }

    /**
     * @throws InsecureCacheDirectoryException
     */
    private function build(
        bool $autoReload,
    ): Environment {
        $environment = new Environment(new ModuleLoader($this->resolver), [
            'cache' => $this->cacheDirectoryGuard->prepare($this->config->cacheDirectory()),
            'auto_reload' => $autoReload,
            'strict_variables' => $this->config->strictVariables(),
            'autoescape' => $this->config->autoescape(),
            'debug' => $this->config->debug(),
            'charset' => $this->config->charset(),
        ]);
        $environment->addExtension($this->templateFunctionExtension);

        return $environment;
    }
}

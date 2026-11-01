<?php

declare(strict_types=1);

namespace Marko\View\Twig;

use Marko\View\Exceptions\InsecureCacheDirectoryException;
use Marko\View\Exceptions\InvalidTemplateException;
use Marko\View\Exceptions\TemplateNotFoundException;
use Marko\View\Exceptions\TemplatePrecompileException;
use Marko\View\TemplatePrecompilerInterface;
use Marko\View\TemplateResolverInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Compiles one Twig template into the environment's cache ahead of time.
 *
 * The environment is built by the same factory as the rendering environment
 * and loads templates through the same ModuleLoader, so the compiled file
 * lands under the key rendering looks up (the resolved absolute path). Auto
 * reload is always on here: a compiled file left over from an earlier deploy
 * is recompiled when its template changed, even when view.auto_refresh is off.
 */
class TwigTemplatePrecompiler implements TemplatePrecompilerInterface
{
    private Environment $environment;

    /**
     * @throws InsecureCacheDirectoryException
     */
    public function __construct(
        TwigEngineFactory $engineFactory,
        private TemplateResolverInterface $resolver,
    ) {
        $this->environment = $engineFactory->create();
        $this->environment->enableAutoReload();
        $this->environment->setLoader(new ModuleLoader($resolver));
    }

    /**
     * @throws TemplatePrecompileException|InvalidTemplateException|TemplateNotFoundException|LoaderError|RuntimeError
     */
    public function precompile(
        string $template,
    ): void {
        try {
            $this->environment->load($template);
        } catch (SyntaxError $exception) {
            throw TemplatePrecompileException::forTemplate(
                template: $template,
                path: $this->resolver->resolve($template),
                line: $exception->getTemplateLine(),
                reason: $exception->getRawMessage(),
                previous: $exception,
            );
        }
    }
}

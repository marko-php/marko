<?php

declare(strict_types=1);

namespace Marko\View\Discovery;

use Marko\View\TemplateFunctionProviderInterface;
use Marko\View\ViewDataProviderInterface;

/**
 * The discovered template function and view data provider classes, in module order.
 */
readonly class ViewProviderClasses
{
    /**
     * @param list<class-string<TemplateFunctionProviderInterface>> $templateFunctionProviders
     * @param list<class-string<ViewDataProviderInterface>> $viewDataProviders
     */
    public function __construct(
        public array $templateFunctionProviders,
        public array $viewDataProviders,
    ) {}
}

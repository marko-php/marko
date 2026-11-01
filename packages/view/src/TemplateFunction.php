<?php

declare(strict_types=1);

namespace Marko\View;

use Closure;
use Marko\View\Exceptions\ViewProviderException;

/**
 * A function callable from every template, whatever the installed engine.
 *
 * Each engine adapter registers it under $name. Its output is escaped like any
 * other value unless $isSafeHtml is true, which declares that the callable
 * returns HTML that must be printed as is (for example a hidden form field).
 */
readonly class TemplateFunction
{
    private const string NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9_]*$/';

    /**
     * @throws ViewProviderException When the name is not a valid function identifier
     */
    public function __construct(
        public string $name,
        public Closure $callable,
        public bool $isSafeHtml = false,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw ViewProviderException::invalidFunctionName($name);
        }
    }
}

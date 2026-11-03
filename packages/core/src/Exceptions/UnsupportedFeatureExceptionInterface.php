<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Throwable;

/**
 * An exception that says a driver cannot do what was asked.
 *
 * Every driver-capability failure in Marko implements this interface, so
 * callers holding it can render the full loud error. The rules behind it:
 *
 * 1. Keep the method in the base interface when it is part of what the
 *    abstraction promises and most drivers can do it.
 * 2. Default: throw loudly through the standard type, naming the feature and
 *    the driver, with a suggestion.
 * 3. A no-op is allowed only when the method's promise still holds, and it
 *    must be documented in the driver's docblock and docs page.
 * 4. Add a capability check only when callers must choose a path before
 *    calling (precedent: ConnectionInterface::supportsReturning()).
 */
interface UnsupportedFeatureExceptionInterface extends Throwable
{
    /**
     * The capability that was requested, e.g. "Presence channels".
     */
    public function getFeature(): string;

    /**
     * The driver that cannot provide it, e.g. "SQLite".
     */
    public function getDriver(): string;

    /**
     * Where the failure happened.
     */
    public function getContext(): string;

    /**
     * How to fix it.
     */
    public function getSuggestion(): string;
}

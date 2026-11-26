<?php

declare(strict_types=1);

namespace Marko\Core\Attributes;

use Attribute;

/**
 * Marks the first parameter of a #[Before] or #[After] plugin method as the
 * receiver of the intercepted instance.
 *
 * ```php
 * #[Before]
 * public function save(#[Subject] Connection $subject, string $sql): void {}
 *
 * #[After]
 * public function save(#[Subject] Connection $subject, mixed $result, string $sql): mixed {}
 * ```
 *
 * The subject is opt-in: plugin methods without it receive only the usual
 * parameters. When used, the subject comes first, followed by the usual
 * parameters (the target's arguments for a before plugin; `$result` and then
 * the arguments for an after plugin). It is only allowed on the first parameter.
 *
 * The value is the intercepted instance the container returned (the
 * interceptor), so it is identical to the instance the rest of the application
 * holds. Calls made on it go through the plugin chain again, so a plugin must
 * not call its own hooked method on it: doing so recurses.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class Subject {}

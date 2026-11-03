<?php

declare(strict_types=1);

namespace Marko\Config;

/**
 * Marks a config key for removal when config files are merged.
 *
 * `null` is an ordinary config value, so an override that sets a key to
 * `null` keeps the key. To delete a key that a lower-priority config file
 * defines, set it to `new RemoveKey()` instead. ConfigMerger removes the
 * key and drops the marker, so it never reaches the merged config.
 */
readonly class RemoveKey {}

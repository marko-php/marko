<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

/**
 * Value object representing a discovered plugin.
 */
readonly class PluginDefinition
{
    /**
     * @param string $pluginClass The fully-qualified class name of the plugin
     * @param string $targetClass The fully-qualified class name of the target being plugged
     * @param array<string, int> $beforeMethods Method names with their sort orders
     * @param array<string, int> $afterMethods Method names with their sort orders
     */
    public function __construct(
        public string $pluginClass,
        public string $targetClass,
        public array $beforeMethods = [],
        public array $afterMethods = [],
    ) {}
}

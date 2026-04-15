<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

interface PluginInterceptedInterface
{
    /**
     * Get the underlying target instance that this interceptor wraps.
     */
    public function getPluginTarget(): object;
}

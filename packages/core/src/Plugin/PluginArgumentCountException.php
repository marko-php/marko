<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Marko\Core\Exceptions\MarkoException;

class PluginArgumentCountException extends MarkoException
{
    public static function wrongCount(
        string $pluginClass,
        string $targetClass,
        string $targetMethod,
        int $expectedCount,
        int $actualCount,
    ): self {
        return new self(
            message: "Plugin \"$pluginClass\" returned $actualCount arguments for \"$targetClass::$targetMethod()\", which expects $expectedCount. Before plugins that modify arguments must return an array matching the target method's parameter count.",
            context: "Plugin \"$pluginClass\" returned $actualCount argument(s) but \"$targetClass::$targetMethod()\" expects $expectedCount.",
            suggestion: "Return an array with exactly $expectedCount elements matching the parameters of $targetClass::$targetMethod()",
        );
    }
}

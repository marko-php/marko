<?php

declare(strict_types=1);

namespace Marko\Layout;

readonly class HandleResolver
{
    public function generate(string $path, string $controllerClass, string $action): string
    {
        $segments = explode('/', trim($path, '/'));
        $routePart = $segments[0] !== '' ? $segments[0] : 'index';

        $shortClass = substr($controllerClass, (int) strrpos($controllerClass, '\\') + 1);
        $controllerPart = strtolower(str_replace('Controller', '', $shortClass));

        return strtolower("{$routePart}_{$controllerPart}_$action");
    }

    public function matches(string $componentHandle, string $pageHandle): bool
    {
        if ($componentHandle === 'default') {
            return true;
        }

        if (str_ends_with($componentHandle, '_*')) {
            $prefix = substr($componentHandle, 0, -1);

            return str_starts_with($pageHandle, $prefix);
        }

        return $componentHandle === $pageHandle;
    }
}

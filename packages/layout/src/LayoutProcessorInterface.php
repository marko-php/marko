<?php

declare(strict_types=1);

namespace Marko\Layout;

use Marko\Layout\Exceptions\AmbiguousSortOrderException;
use Marko\Layout\Exceptions\CircularSlotException;
use Marko\Layout\Exceptions\LayoutNotFoundException;
use Marko\Layout\Exceptions\SlotNotFoundException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

interface LayoutProcessorInterface
{
    /**
     * Process a controller action through the full layout render pipeline.
     *
     * @param class-string $controllerClass
     * @param array<string, mixed> $routeParameters
     * @throws SlotNotFoundException
     * @throws CircularSlotException
     * @throws LayoutNotFoundException
     * @throws AmbiguousSortOrderException
     */
    public function process(
        string $controllerClass,
        string $action,
        string $routePath,
        array $routeParameters,
        Request $request,
    ): Response;
}

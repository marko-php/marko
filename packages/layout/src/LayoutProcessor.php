<?php

declare(strict_types=1);

namespace Marko\Layout;

use Marko\Layout\Exceptions\AmbiguousSortOrderException;
use Marko\Layout\Exceptions\CircularSlotException;
use Marko\Layout\Exceptions\LayoutNotFoundException;
use Marko\Layout\Exceptions\SlotNotFoundException;
use Marko\Core\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

readonly class LayoutProcessor implements LayoutProcessorInterface
{
    public function __construct(
        private ContainerInterface $container,
        private LayoutResolver $layoutResolver,
        private HandleResolver $handleResolver,
        private ComponentCollectorInterface $componentCollector,
        private ComponentDataResolver $componentDataResolver,
        private ViewInterface $view,
    ) {}

    /**
     * Process a controller action through the full layout render pipeline.
     *
     * @param class-string $controllerClass
     * @param array<string, mixed> $routeParameters
     * @throws SlotNotFoundException
     * @throws CircularSlotException
     * @throws LayoutNotFoundException
     * @throws AmbiguousSortOrderException|ContainerExceptionInterface
     */
    public function process(
        string $controllerClass,
        string $action,
        string $routePath,
        array $routeParameters,
        Request $request,
    ): Response {
        // 1. Resolve the layout component
        $layoutResult = $this->layoutResolver->resolve($controllerClass, $action);
        $layoutDefinition = $layoutResult['attribute'];
        $layoutComponentClass = $layoutResult['componentClass'];

        // 2. Generate page handle
        $handle = $this->handleResolver->generate($routePath, $controllerClass, $action);

        // 3. Collect matching components
        $collection = $this->componentCollector->collect([], $handle);

        // 4. Build the complete slot graph: layout slots + component-defined sub-slots
        $allDefinedSlots = $layoutDefinition->slots;

        foreach ($collection->all() as $definition) {
            foreach ($definition->slots as $subSlot) {
                $allDefinedSlots[] = $subSlot;
            }
        }

        // 5. Validate all component slots exist in the graph
        foreach ($collection->all() as $definition) {
            if ($definition->slot !== null && !in_array($definition->slot, $allDefinedSlots, true)) {
                throw SlotNotFoundException::forSlot($definition->slot, $layoutComponentClass);
            }
        }

        // 6. Detect circular references in the slot graph
        $this->detectCircularReferences($collection, $layoutDefinition->slots);

        // 7. Render each top-level slot using multi-pass (fills sub-slots recursively)
        $slots = [];

        foreach ($layoutDefinition->slots as $slotName) {
            $slots[$slotName] = $this->renderSlot($slotName, $collection, $routeParameters, $request);
        }

        // 8. Render the layout template with assembled slots
        $layoutHtml = $this->view->renderToString($layoutDefinition->template, ['slots' => $slots]);

        // 9. Return HTML response
        return Response::html($layoutHtml);
    }

    /**
     * Render all components in a slot and fill any sub-slots they define.
     *
     * @param array<string, mixed> $routeParameters
     * @throws AmbiguousSortOrderException|ContainerExceptionInterface
     */
    private function renderSlot(
        string $slotName,
        ComponentCollection $collection,
        array $routeParameters,
        Request $request,
    ): string {
        $slotComponents = $collection->forSlot($slotName);
        $html = '';

        foreach ($slotComponents as $definition) {
            $componentInstance = $this->container->get($definition->className);
            $data = $this->componentDataResolver->resolve($componentInstance, $routeParameters, $request);
            $componentHtml = $this->view->renderToString($definition->template, $data);

            // If this component defines sub-slots, fill them in
            if ($definition->hasSubSlots()) {
                foreach ($definition->slots as $subSlot) {
                    $subSlotHtml = $this->renderSlot($subSlot, $collection, $routeParameters, $request);
                    $placeholder = '{slot ' . $subSlot . '}{/slot}';
                    $componentHtml = str_replace($placeholder, $subSlotHtml, $componentHtml);
                }
            }

            $html .= $componentHtml;
        }

        return $html;
    }

    /**
     * Detect circular references in the slot graph via depth-first traversal.
     *
     * @param array<string> $topLevelSlots
     * @throws CircularSlotException
     */
    private function detectCircularReferences(ComponentCollection $collection, array $topLevelSlots): void
    {
        // Build a map: slot name -> sub-slots it leads to (via components in that slot)
        /** @var array<string, array<string>> $slotToSubSlots */
        $slotToSubSlots = [];

        foreach ($collection->all() as $definition) {
            if ($definition->slot === null || !$definition->hasSubSlots()) {
                continue;
            }

            $slotToSubSlots[$definition->slot] ??= [];

            foreach ($definition->slots as $subSlot) {
                $slotToSubSlots[$definition->slot][] = $subSlot;
            }
        }

        // DFS from each top-level slot
        foreach ($topLevelSlots as $slot) {
            $this->dfsCheckCycle($slot, $slotToSubSlots, [], []);
        }
    }

    /**
     * @param array<string, array<string>> $slotToSubSlots
     * @param array<string> $visiting  — current DFS path (for cycle detection)
     * @param array<string> $visited   — fully processed nodes (for efficiency)
     * @throws CircularSlotException
     */
    private function dfsCheckCycle(
        string $slot,
        array $slotToSubSlots,
        array $visiting,
        array $visited,
    ): void {
        if (in_array($slot, $visiting, true)) {
            $chain = [...$visiting, $slot];
            throw CircularSlotException::forSlot($slot, $chain);
        }

        if (in_array($slot, $visited, true)) {
            return;
        }

        $visiting[] = $slot;

        foreach ($slotToSubSlots[$slot] ?? [] as $subSlot) {
            $this->dfsCheckCycle($subSlot, $slotToSubSlots, $visiting, $visited);
        }
    }
}

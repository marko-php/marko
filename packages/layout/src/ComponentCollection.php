<?php

declare(strict_types=1);

namespace Marko\Layout;

use Marko\Layout\Exceptions\AmbiguousSortOrderException;
use Marko\Layout\Exceptions\ComponentNotFoundException;
use Marko\Layout\Exceptions\DuplicateComponentException;

class ComponentCollection
{
    /** @var array<string, ComponentDefinition> */
    private array $components = [];

    /**
     * @throws DuplicateComponentException
     */
    public function add(ComponentDefinition $definition): void
    {
        if (isset($this->components[$definition->className])) {
            throw DuplicateComponentException::duplicate($definition->className);
        }

        $this->components[$definition->className] = $definition;
    }

    /**
     * @throws ComponentNotFoundException
     */
    public function remove(string $className): void
    {
        if (!isset($this->components[$className])) {
            throw ComponentNotFoundException::forComponent($className);
        }

        unset($this->components[$className]);
    }

    /**
     * @throws ComponentNotFoundException
     */
    public function get(string $className): ComponentDefinition
    {
        if (!isset($this->components[$className])) {
            throw ComponentNotFoundException::forComponent($className);
        }

        return $this->components[$className];
    }

    /**
     * @return array<string, ComponentDefinition>
     */
    public function all(): array
    {
        return $this->components;
    }

    /**
     * @return array<int, ComponentDefinition>
     * @throws AmbiguousSortOrderException
     */
    public function forSlot(string $slot): array
    {
        $filtered = array_values(
            array_filter(
                $this->components,
                fn (ComponentDefinition $def): bool => $def->slot === $slot,
            ),
        );

        return $this->sort($filtered, $slot);
    }

    /**
     * @throws ComponentNotFoundException
     */
    public function move(string $className, string $newSlot, ?int $newSortOrder = null): void
    {
        $definition = $this->get($className);

        $sortOrder = $newSortOrder ?? $definition->sortOrder;

        $updated = new ComponentDefinition(
            className: $definition->className,
            template: $definition->template,
            slot: $newSlot,
            handles: $definition->handles,
            slots: $definition->slots,
            sortOrder: $sortOrder,
            before: $definition->before,
            after: $definition->after,
        );

        $this->components[$className] = $updated;
    }

    public function count(): int
    {
        return count($this->components);
    }

    /**
     * @return array<string, array<int, ComponentDefinition>>
     */
    public function groupedBySlot(): array
    {
        $grouped = [];

        foreach ($this->components as $definition) {
            if ($definition->slot === null) {
                continue;
            }

            $grouped[$definition->slot][] = $definition;
        }

        return $grouped;
    }

    /**
     * @param array<int, ComponentDefinition> $components
     * @return array<int, ComponentDefinition>
     * @throws AmbiguousSortOrderException
     */
    private function sort(array $components, string $slot): array
    {
        // First pass: sort by sortOrder, detecting ambiguities
        usort($components, function (ComponentDefinition $a, ComponentDefinition $b) use ($slot): int {
            if ($a->sortOrder === $b->sortOrder) {
                $aResolved = $a->before !== null || $a->after !== null;
                $bResolved = $b->before !== null || $b->after !== null;

                if (!$aResolved && !$bResolved) {
                    throw AmbiguousSortOrderException::forComponents(
                        slot: $slot,
                        sortOrder: $a->sortOrder,
                        components: [$a->className, $b->className],
                    );
                }
            }

            return $a->sortOrder <=> $b->sortOrder;
        });

        // Second pass: apply before/after constraints
        return $this->applyConstraints($components);
    }

    /**
     * @param array<int, ComponentDefinition> $components
     * @return array<int, ComponentDefinition>
     */
    private function applyConstraints(array $components): array
    {
        $classNames = array_map(fn (ComponentDefinition $def): string => $def->className, $components);

        foreach ($components as $def) {
            if ($def->before !== null) {
                $targetIndex = array_search($def->before, $classNames, true);
                $currentIndex = array_search($def->className, $classNames, true);

                if ($targetIndex !== false && $currentIndex !== false && $currentIndex > $targetIndex) {
                    // Move current before target
                    array_splice($classNames, (int) $currentIndex, 1);
                    $targetIndex = (int) array_search($def->before, $classNames, true);
                    array_splice($classNames, (int) $targetIndex, 0, [$def->className]);
                }
            }

            if ($def->after !== null) {
                $targetIndex = array_search($def->after, $classNames, true);
                $currentIndex = array_search($def->className, $classNames, true);

                if ($targetIndex !== false && $currentIndex !== false && $currentIndex < $targetIndex) {
                    // Move current after target
                    array_splice($classNames, (int) $currentIndex, 1);
                    $targetIndex = (int) array_search($def->after, $classNames, true);
                    array_splice($classNames, (int) $targetIndex + 1, 0, [$def->className]);
                }
            }
        }

        $indexed = [];

        foreach ($components as $def) {
            $indexed[$def->className] = $def;
        }

        return array_values(
            array_map(fn (string $className): ComponentDefinition => $indexed[$className], $classNames),
        );
    }
}

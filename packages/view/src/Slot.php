<?php

declare(strict_types=1);

namespace Marko\View;

/**
 * The engine-agnostic contract behind layout slots.
 *
 * A layout template receives a `slots` array of pre-rendered HTML keyed by slot
 * name, and each view driver's slot syntax prints one entry. A component
 * template rendered without `slots` prints the placeholder instead, which the
 * layout processor replaces when it fills that component's sub-slots.
 */
class Slot
{
    /**
     * The text a layout looks for when it fills a sub-slot.
     */
    public static function placeholder(
        string $name,
    ): string {
        return '{slot ' . $name . '}{/slot}';
    }

    /**
     * Render one slot: its HTML when slots were passed, else its placeholder.
     *
     * @param array<string, string>|null $slots
     */
    public static function render(
        ?array $slots,
        string $name,
    ): string {
        if ($slots === null) {
            return self::placeholder($name);
        }

        return $slots[$name] ?? '';
    }
}

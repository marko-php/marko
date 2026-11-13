<?php

declare(strict_types=1);

namespace Marko\View\Twig;

use Marko\View\Slot;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Adds the `slot()` function that layout templates use to mark where child
 * components render: `{{ slot('content') }}`. It is the Twig counterpart of
 * Latte's `{slot content/}` tag, and prints the slot's pre-rendered HTML
 * unescaped.
 */
class SlotExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'slot',
                /** @param array<string, mixed> $context */
                static function (array $context, string $name): string {
                    $slots = $context['slots'] ?? null;

                    return Slot::render(is_array($slots) ? $slots : null, $name);
                },
                ['needs_context' => true, 'is_safe' => ['html']],
            ),
        ];
    }
}

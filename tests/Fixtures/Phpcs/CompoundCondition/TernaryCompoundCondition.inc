<?php

declare(strict_types=1);

function ternaryCompoundCondition(
    ?string $name,
    int $count,
): string {
    $label = $name === null || $count === 0 ? 'none' : $name;

    return ($name !== null && $count > 0)
        ? $name
        : $label;
}

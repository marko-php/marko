<?php

declare(strict_types=1);

function whileOneLine(
    array $items,
    int $limit,
): void {
    while ($items !== [] && $limit > 0) {
        array_pop($items);
        $limit--;
    }
}

<?php

declare(strict_types=1);

function singleCheck(
    ?string $userAgent,
    array $items,
): ?string {
    $count = array_any($items, fn (string $item): bool => $item === 'a' || $item === 'b') ? 1 : 0;

    return $userAgent === null ? null : mb_substr($userAgent, 0, 255 + $count);
}

<?php

declare(strict_types=1);

function returnOneLine(
    ?string $name,
    int $count,
): bool {
    return $name !== null && $count > 0;
}

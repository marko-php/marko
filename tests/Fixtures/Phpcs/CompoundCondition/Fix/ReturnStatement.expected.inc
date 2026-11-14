<?php

declare(strict_types=1);

function returnStatement(
    ?string $name,
    int $count,
): bool {
    return $name !== null
        && $count > 0
        && $count < 10;
}

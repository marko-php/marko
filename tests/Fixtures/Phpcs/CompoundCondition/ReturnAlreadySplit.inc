<?php

declare(strict_types=1);

function returnAlreadySplit(
    ?string $name,
    int $count,
    bool $force,
): bool {
    return $name !== null
        && $count > 0
        || $force;
}

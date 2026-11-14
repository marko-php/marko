<?php

declare(strict_types=1);

function commentBetweenOperands(
    ?string $name,
    int $count,
): void {
    if (
        $name !== null // a name is required
        && $count > 0
    ) {
        echo $name;
    }
}

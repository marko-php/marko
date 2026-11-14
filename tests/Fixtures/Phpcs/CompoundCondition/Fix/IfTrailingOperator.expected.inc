<?php

declare(strict_types=1);

function ifTrailingOperator(
    ?string $name,
    int $count,
): void {
    if (
        $name !== null
        && $count > 0
    ) {
        echo $name;
    }
}

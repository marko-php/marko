<?php

declare(strict_types=1);

function ifNestedGroup(
    ?string $name,
    int $count,
    bool $force,
): void {
    if ($name !== null && ($count > 0 || $force)) {
        echo $name;
    }
}

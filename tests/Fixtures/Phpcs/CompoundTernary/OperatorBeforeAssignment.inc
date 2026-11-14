<?php

declare(strict_types=1);

function operatorBeforeAssignment(
    bool $a,
    bool $c,
): bool {
    $x = $a || $b = $c ? 1 : 2;

    return $x;
}

<?php

declare(strict_types=1);

function logicalAndBeforeTernary(
    bool $a,
    bool $b,
): bool {
    $ok = $a and $b ? 1 : 2;

    return $ok;
}

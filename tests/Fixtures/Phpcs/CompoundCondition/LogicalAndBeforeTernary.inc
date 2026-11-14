<?php

declare(strict_types=1);

function logicalAndBeforeTernary(
    bool $first,
    bool $second,
): bool {
    $ok = $first and $second ? 1 : 2;

    return $ok;
}

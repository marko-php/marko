<?php

declare(strict_types=1);

function nestedGroup(
    bool $a,
    bool $b,
    bool $c,
): bool {
    return $a && ($b || $c);
}

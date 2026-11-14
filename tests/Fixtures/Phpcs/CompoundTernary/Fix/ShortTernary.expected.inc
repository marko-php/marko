<?php

declare(strict_types=1);

function shortTernary(
    bool $a,
    bool $b,
): bool|string {
    return ($a || $b)
        ?: 'neither';
}

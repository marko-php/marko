<?php

declare(strict_types=1);

function nestedTernaryInTrueBranch(
    bool $a,
    bool $b,
    bool $c,
): string {
    return ($a || $b)
        ? ($c ? 'c' : 'b')
        : 'none';
}

<?php

declare(strict_types=1);

function insideControlStructure(
    bool $a,
    bool $b,
    bool $c,
): void {
    if ($a || $b ? $c : !$c) {
        echo 'if';
    } elseif ($a && $b ? $c : !$c) {
        echo 'elseif';
    }

    while ($a || $b ? $c : !$c) {
        $c = !$c;
    }

    do {
        $c = !$c;
    } while ($a && $b ? $c : !$c);
}

<?php

declare(strict_types=1);

function ternaryBoundaries(
    bool $a,
    bool $b,
    bool $c,
    bool $d,
    array $list,
): iterable {
    var_dump($a && $b, $c || $d ? 1 : 2);
    var_dump($a && $b || ($c || $d ? 1 : 2));
    $value = $a && $list[$c || $d ? 1 : 2];
    $map = [$a && $b => $c || $d ? 1 : 2];
    $match = match (true) {
        $a && $b => $c || $d ? 1 : 2,
        default => 0,
    };
    $callback = $a && fn (): int => $c || $d ? 1 : 2;
    $a && $b; $c || $d ? var_dump(1) : var_dump(2);
    for ($i = $a && $b; $i;) { $c || $d ? var_dump(1) : var_dump(2); }
    { $a && $b; } $c || $d ? var_dump(1) : var_dump(2);
    $a && print $c || $d ? 1 : 2;
    $yielded = $a && yield $c || $d ? 1 : 2;
    $delegated = $a && yield from $c || $d ? [1] : [2];
    $thrown = $a && throw $c || $d ? new Exception() : new Error();
    $nested = $a && match (true) { default => $b } ? 1 : 2;

    return [$value, $map, $match, $callback, $yielded, $delegated, $thrown, $nested];
}

<?php

declare(strict_types=1);

function keywordOperators(
    ?string $name,
    int $count,
): void {
    $name !== null and print $name;
    $count > 0 or print 'empty';
}

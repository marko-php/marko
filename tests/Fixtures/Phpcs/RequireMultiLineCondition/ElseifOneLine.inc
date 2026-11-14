<?php

declare(strict_types=1);

function elseifOneLine(
    ?string $name,
    int $count,
): void {
    if ($name === null) {
        echo 'none';
    } elseif ($name === '' || $count === 0) {
        echo 'empty';
    }
}

<?php

declare(strict_types=1);

function ifHangingSingleCheck(string $name): void
{
    if (in_array(
        $name,
        ['first', 'second'],
        true,
    )) {
        echo $name;
    }
}

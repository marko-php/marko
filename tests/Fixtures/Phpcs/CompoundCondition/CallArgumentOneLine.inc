<?php

declare(strict_types=1);

function callArgumentOneLine(
    ?string $name,
    int $count,
): void {
    var_dump($name !== null && $count > 0);
}

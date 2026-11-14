<?php

declare(strict_types=1);

function assignmentOneLine(
    ?string $name,
    bool $force,
): bool {
    $isValid = $name !== null || $force;

    return $isValid;
}

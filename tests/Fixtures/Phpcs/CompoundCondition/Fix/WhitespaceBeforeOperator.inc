<?php

declare(strict_types=1);

function whitespaceBeforeOperator(
    ?string $name,
    int $count,
): bool {
    $named = $name !== null /* checked first */   && $name !== '';

    return $named    && $count > 0;
}

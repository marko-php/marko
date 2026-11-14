<?php

declare(strict_types=1);

function arrayValueOneLine(
    ?string $name,
    int $count,
): array {
    return [
        'valid' => $name !== null && $count > 0,
    ];
}

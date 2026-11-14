<?php

declare(strict_types=1);

function matchArmOneLine(
    ?string $name,
    int $count,
): string {
    return match (true) {
        $name !== null && $count > 0 => 'valid',
        default => 'invalid',
    };
}

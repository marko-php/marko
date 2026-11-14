<?php

declare(strict_types=1);

function arrowFunctionOneLine(array $users): array
{
    return array_filter(
        $users,
        fn (?string $name): bool => $name !== null && $name !== '',
    );
}

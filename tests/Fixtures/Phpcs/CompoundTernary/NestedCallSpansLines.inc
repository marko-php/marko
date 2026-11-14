<?php

declare(strict_types=1);

function nestedCallSpansLines(
    ?string $userAgent,
): ?string {
    return ($userAgent === null || in_array(
        $userAgent,
        ['curl', 'wget'],
        true,
    ))
        ? null
        : $userAgent;
}

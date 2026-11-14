<?php

declare(strict_types=1);

function conditionSplit(
    ?string $userAgent,
): ?string {
    return ($userAgent === null
        || $userAgent === '')
        ? null
        : mb_substr($userAgent, 0, 255);
}

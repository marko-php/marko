<?php

declare(strict_types=1);

function arrayValue(
    bool $isNew,
    bool $isRequired,
): array {
    return [
        'required' => $isNew
            && $isRequired,
    ];
}

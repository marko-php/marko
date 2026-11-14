<?php

declare(strict_types=1);

function ifHangingCondition(mixed $data): void
{
    if (
        !is_array($data)
        || !isset($data['value'])
        || !isset($data['expires'])
    ) {
        echo 'invalid';
    }
}

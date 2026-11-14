<?php

declare(strict_types=1);

function namedArgument(
    bool $short,
    bool $quiet,
): string {
    return str_repeat(
        times: $short || $quiet ? 1 : 3,
        string: '-',
    );
}

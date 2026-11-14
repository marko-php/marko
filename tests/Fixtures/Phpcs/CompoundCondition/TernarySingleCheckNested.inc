<?php

declare(strict_types=1);

function ternarySingleCheckNested(
    array $list,
    bool $enabled,
): string {
    return array_any($list, fn (bool $item): bool => $item && $enabled) ? 'some' : 'none';
}

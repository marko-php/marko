<?php

declare(strict_types=1);

function arrowFunctionArgument(array $packages): array
{
    return array_filter(
        $packages,
        fn (string $package) => $package !== 'php' && !str_starts_with($package, 'ext-'),
    );
}

<?php

declare(strict_types=1);

function echoTernary(
    bool $a,
    bool $b,
): void {
    echo $a || $b ? 'yes' : 'no';
}

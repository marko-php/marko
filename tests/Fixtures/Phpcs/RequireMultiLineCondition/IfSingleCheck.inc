<?php

declare(strict_types=1);

function ifSingleCheck(?string $name): void
{
    if ($name !== null) {
        echo $name;
    }
}

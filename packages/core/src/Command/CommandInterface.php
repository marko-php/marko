<?php

declare(strict_types=1);

namespace Marko\Core\Command;

interface CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int;
}

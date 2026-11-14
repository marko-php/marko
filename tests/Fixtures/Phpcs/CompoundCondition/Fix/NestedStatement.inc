<?php

declare(strict_types=1);

class NestedStatement
{
    public function check(
        array $input,
        bool $enabled,
    ): bool {
        if ($enabled) {
            $force = isset($input['force']) || isset($input['f']);

            return $force;
        }

        return false;
    }
}

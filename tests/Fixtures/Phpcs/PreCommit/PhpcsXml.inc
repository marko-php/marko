<?php

declare(strict_types=1);

class PhpcsXml
{
    public function check(
        bool $isNew,
        bool $isRequired,
    ): string {
        if ($isNew && $isRequired) {
            return 'both';
        }

        $isEither = $isNew || $isRequired;

        return $isNew || $isRequired ? 'either' : 'neither';
    }
}

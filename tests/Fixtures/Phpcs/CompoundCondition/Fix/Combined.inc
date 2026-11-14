<?php

declare(strict_types=1);

class Combined
{
    public function check(
        mixed $data,
        array $packages,
        bool $isNew,
        bool $isRequired,
        ?string $name,
    ): array {
        if (!is_array($data)
            || !isset($data['value'])) {
            return [];
        }

        if (
            $name !== null && // a name is required
            $name !== ''
        ) {
            var_dump($isNew && $isRequired);
        }

        $force = isset($data['force']) || isset($data['f']) or $isNew;

        return [
            'required' => $isNew && ($isRequired || $force),
            'packages' => array_filter(
                $packages,
                fn (string $package) => $package !== 'php' && !str_starts_with($package, 'ext-'),
            ),
        ];
    }
}

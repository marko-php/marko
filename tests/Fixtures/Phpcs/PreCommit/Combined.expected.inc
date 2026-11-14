<?php

declare(strict_types=1);

namespace Fixture;

class Combined
{
    public function describe(
        array $items,
        ?string $name,
        bool $isNew,
        bool $isRequired,
    ): array {
        if (
            $name !== null
            && $name !== ''
        ) {
            $name = trim($name);
        }

        if (
            in_array(
                $name,
                array_filter(
                    $items,
                    fn ($item) => $item->active
                    && $item->visible,
                ),
            )
        ) {
            $isNew = false;
        }

        $visible = array_filter($items, fn ($item) => $item !== null
            && $item->visible, ARRAY_FILTER_USE_BOTH);

        $label = match (true) {
            $isNew
                && $isRequired => 'new and required',
            default => 'other',
        };

        $title = ($name === null || $name === '')
            ? 'Untitled'
            : $name;

        $age = $isNew ? 'new' : 'old';

        $answer = sprintf(format: '%s', values: ($isNew || $isRequired)
            ? 'yes'
            : 'no');

        return [
            'required' => $isNew
                && $isRequired,
            'visible' => $visible,
            'label' => $label,
            'title' => $title,
            'age' => $age,
            'answer' => $answer,
        ];
    }

    public function isActive(
        bool $isNew,
        bool $isRequired,
    ): bool {
        return $isNew
            && $isRequired;
    }
}

<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use JsonException;

class InertiaCollector
{
    /**
     * @return array<string, mixed>|null
     */
    public function collect(string $body): ?array
    {
        $page = $this->jsonPage($body);
        $mode = 'json';

        if ($page === null) {
            $page = $this->htmlPage($body);
            $mode = 'html';
        }

        if ($page === null) {
            return null;
        }

        $props = is_array($page['props'] ?? null) ? $page['props'] : [];
        $component = $this->stringValue($page['component'] ?? null, 'unknown');

        return [
            'label' => 'Inertia',
            'badge' => $component,
            'mode' => $mode,
            'component' => $component,
            'url' => $this->stringValue($page['url'] ?? null, ''),
            'version' => $this->stringValue($page['version'] ?? null, ''),
            'props_count' => count($props),
            'prop_keys' => array_values(array_filter(array_keys($props), 'is_string')),
            'partial_component' => $this->serverHeader('X-Inertia-Partial-Component'),
            'partial_data' => $this->serverHeader('X-Inertia-Partial-Data'),
            'partial_except' => $this->serverHeader('X-Inertia-Partial-Except'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonPage(string $body): ?array
    {
        $trimmed = ltrim($body);

        if (! str_starts_with($trimmed, '{')) {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return $this->pageArray($decoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function htmlPage(string $body): ?array
    {
        if (preg_match('/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/is', $body, $matches) === 1) {
            try {
                $decoded = json_decode(trim($matches[1]), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $decoded = null;
            }

            $page = $this->pageArray($decoded);

            if ($page !== null) {
                return $page;
            }
        }

        if (preg_match('/\sdata-page="([^"]+)"/i', $body, $matches) !== 1) {
            return null;
        }

        $json = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');

        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return $this->pageArray($decoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pageArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (! is_string($value['component'] ?? null) || ! array_key_exists('props', $value)) {
            return null;
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function serverHeader(string $name): ?string
    {
        $key = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    private function stringValue(
        mixed $value,
        string $default,
    ): string {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }
}

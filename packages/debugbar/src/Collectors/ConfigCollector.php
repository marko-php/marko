<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;

class ConfigCollector implements CollectorInterface
{
    public function name(): string
    {
        return 'config';
    }

    public function collect(Debugbar $debugbar): array
    {
        $config = $this->mask(
            values: $debugbar->config()->all(),
            patterns: $debugbar->configArray('debugbar.options.config.masked', []),
        );

        return [
            'label' => 'Config',
            'badge' => count($config),
            'config' => $config,
        ];
    }

    /**
     * @param array<mixed> $values
     * @param array<mixed> $patterns
     * @return array<mixed>
     */
    private function mask(
        array $values,
        array $patterns,
        string $prefix = '',
    ): array
    {
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.(string) $key;

            if ($this->matches($path, $patterns)) {
                $values[$key] = '[masked]';
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->mask($value, $patterns, $path);
            }
        }

        return $values;
    }

    /**
     * @param array<mixed> $patterns
     */
    private function matches(
        string $path,
        array $patterns,
    ): bool
    {
        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            $regex = '/^'.str_replace('\\*', '.+', preg_quote($pattern, '/')).'$/';

            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Marko\Scope\Storage;

use BackedEnum;
use DateTimeImmutable;
use JsonException;
use Marko\Scope\Exceptions\ScopeConfigurationException;

readonly class ScopedDataSerializer
{
    /**
     * Serialize an overrides map to JSON, or null if empty.
     *
     * @param array<string, array<string, mixed>> $overrides
     * @throws JsonException
     */
    public function serialize(array $overrides): ?string
    {
        if ($overrides === []) {
            return null;
        }

        $converted = [];
        foreach ($overrides as $scopeKey => $properties) {
            $converted[$scopeKey] = [];
            foreach ($properties as $property => $value) {
                $converted[$scopeKey][$property] = $this->convertToJsonValue($value);
            }
        }

        return json_encode($converted, JSON_THROW_ON_ERROR);
    }

    private function convertToJsonValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    /**
     * Deserialize a JSON string back to an overrides map.
     *
     * @return array<string, array<string, mixed>>
     * @throws ScopeConfigurationException
     */
    public function deserialize(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        try {
            /** @var array<string, array<string, mixed>> $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ScopeConfigurationException(
                message: "Malformed scopes JSON: {$e->getMessage()}",
                context: 'Deserializing scopes column JSON',
                suggestion: 'Ensure the scopes column contains valid JSON matching the {"axis:path": {"property": value}} shape',
            );
        }

        return $decoded;
    }
}

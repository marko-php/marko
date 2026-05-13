<?php

declare(strict_types=1);

use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Storage\ScopedDataSerializer;

enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

it('serializes an empty overrides map to null rather than empty object', function (): void {
    $serializer = new ScopedDataSerializer();

    expect($serializer->serialize([]))->toBeNull();
});

it('serializes nested overrides keyed by axis colon path', function (): void {
    $serializer = new ScopedDataSerializer();
    $overrides = [
        'geo:eu.de' => ['name' => 'Hemd', 'price' => 19.99],
        'locale:de' => ['name' => 'Hallo'],
    ];

    $json = $serializer->serialize($overrides);

    expect($json)->toBe('{"geo:eu.de":{"name":"Hemd","price":19.99},"locale:de":{"name":"Hallo"}}');
});

it('deserializes valid JSON into a flat array structure', function (): void {
    $serializer = new ScopedDataSerializer();
    $json = '{"geo:eu.de":{"name":"Hemd","price":19.99},"locale:de":{"name":"Hallo"}}';

    $overrides = $serializer->deserialize($json);

    expect($overrides)->toBe([
        'geo:eu.de' => ['name' => 'Hemd', 'price' => 19.99],
        'locale:de' => ['name' => 'Hallo'],
    ]);
});

it('round-trips BackedEnum values via their backing scalar', function (): void {
    $serializer = new ScopedDataSerializer();
    $overrides = [
        'store:default' => ['status' => TestStatus::Active],
    ];

    $json = $serializer->serialize($overrides);
    $result = $serializer->deserialize($json);

    expect($result)->toBe([
        'store:default' => ['status' => 'active'],
    ]);
});

it('round-trips DateTimeImmutable values via formatted string', function (): void {
    $serializer = new ScopedDataSerializer();
    $date = new DateTimeImmutable('2024-03-15 10:30:00');
    $overrides = [
        'store:default' => ['published_at' => $date],
    ];

    $json = $serializer->serialize($overrides);
    $result = $serializer->deserialize($json);

    expect($result)->toBe([
        'store:default' => ['published_at' => '2024-03-15 10:30:00'],
    ]);
});

it('throws ScopeConfigurationException when deserialized JSON is malformed', function (): void {
    $serializer = new ScopedDataSerializer();

    expect(fn () => $serializer->deserialize('{not valid json'))
        ->toThrow(ScopeConfigurationException::class);
});

it('deserializes null or empty string into an empty overrides map', function (): void {
    $serializer = new ScopedDataSerializer();

    expect($serializer->deserialize(null))->toBeEmpty()
        ->and($serializer->deserialize(''))->toBeEmpty();
});

<?php

declare(strict_types=1);

use Marko\Queue\Rabbitmq\Exchange\ExchangeType;

it('defines all four exchange types as enum cases', function (): void {
    $cases = ExchangeType::cases();

    expect($cases)->toHaveCount(4)
        ->and(ExchangeType::Direct)->toBeInstanceOf(ExchangeType::class)
        ->and(ExchangeType::Fanout)->toBeInstanceOf(ExchangeType::class)
        ->and(ExchangeType::Topic)->toBeInstanceOf(ExchangeType::class)
        ->and(ExchangeType::Headers)->toBeInstanceOf(ExchangeType::class);
});

it('backs exchange types with AMQP string values', function (): void {
    expect(ExchangeType::Direct->value)->toBe('direct')
        ->and(ExchangeType::Fanout->value)->toBe('fanout')
        ->and(ExchangeType::Topic->value)->toBe('topic')
        ->and(ExchangeType::Headers->value)->toBe('headers');
});

it('provides exchange type value for AMQP declaration', function (): void {
    $type = ExchangeType::Topic;

    expect($type->value)->toBeString()
        ->and($type->value)->toBe('topic');
});
